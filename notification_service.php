<?php
/**
 * notification_service.php
 *
 * Central notification service for HarvHub.
 *
 * Responsibilities:
 *  - Store notifications in contract_notifications.
 *  - Keep harvhub.unread_notifications as a per-sub-account unread flag.
 *  - Send notification emails through notification_email.php.
 *  - Store browser/sound permission preferences globally per user email.
 *
 * Include this file from any PHP action that needs to create a notification:
 *     require_once __DIR__ . '/notification_service.php';
 *     recordContractNotification($pdo, [...]);
 */


/* ============================================================
 * CLEAN NOTIFICATION TEXT
 * ============================================================ */

if (!function_exists('harvhubNotificationStripTechWords')) {
    /**
     * Remove technical / code / database / script related words
     * so emails never contain developer-facing language.
     *
     * Examples of stripped words:
     *  php, sql, mysql, pdo, database, db, query, script,
     *  array, json, html, css, js, javascript, code, function,
     *  class, method, variable, loop, if, else, echo, print,
     *  var, const, let, table, column, row, insert, update,
     *  delete, select, where, join, schema, api, endpoint,
     *  http, https, url, www, com, net, org, io
     */
    function harvhubNotificationStripTechWords(string $text): string
    {
        if ($text === '') return '';

        $techWords = [
            'php','sql','mysql','pdo','database','db','query','script',
            'array','json','html','css','js','javascript','code','function',
            'class','method','variable','loop','if','else','echo','print',
            'var','const','let','table','column','row','insert','update',
            'delete','select','where','join','schema','api','endpoint',
            'http','https','url','www','com','net','org','io'
        ];

        $pattern = '/\b(' . implode('|', array_map('preg_quote', $techWords)) . ')\b/i';
        $cleaned = preg_replace($pattern, '', $text);

        // Collapse multiple spaces left behind by removed words
        $cleaned = preg_replace('/\s{2,}/', ' ', $cleaned);
        return trim($cleaned);
    }
}

if (!function_exists('harvhubNotificationCleanText')) {
    function harvhubNotificationCleanText($value): string
    {
        $value = (string)$value;

        // Remove emojis / pictographs
        $value = preg_replace(
            '/[\x{1F300}-\x{1FAFF}]/u',
            '',
            $value
        );

        // Remove technical / code / database / script words
        $value = harvhubNotificationStripTechWords($value);

        return trim($value);
    }
}


/* ============================================================
 * GET NOTIFICATION PREFERENCES
 * ============================================================ */

if (!function_exists('getNotificationPreferences')) {
    function getNotificationPreferences(
        PDO $pdo,
        string $email,
        int $mainAccountId = 0
    ): array {

        $email = strtolower(trim($email));

        try {

            $q = $pdo->prepare("
                SELECT *
                FROM notification_preferences
                WHERE LOWER(user_email) = ?
                LIMIT 1
            ");

            $q->execute([$email]);

            $row = $q->fetch(PDO::FETCH_ASSOC);

            if (!$row) {

                $ins = $pdo->prepare("
                    INSERT INTO notification_preferences
                    (
                        user_email,
                        main_account_id,
                        browser_notifications_enabled,
                        notification_sound_enabled,
                        permission_prompt_seen
                    )
                    VALUES (?, ?, 0, 0, 0)
                ");

                $ins->execute([
                    $email,
                    $mainAccountId > 0 ? $mainAccountId : null
                ]);

                $q->execute([$email]);

                $row = $q->fetch(PDO::FETCH_ASSOC);
            }

            return $row ?: [
                'user_email' => $email,
                'main_account_id' => $mainAccountId,
                'browser_notifications_enabled' => 0,
                'notification_sound_enabled' => 0,
                'permission_prompt_seen' => 0
            ];

        } catch (Throwable $e) {

            error_log(
                '[HarvHub Notification Service] getNotificationPreferences failed: '
                . $e->getMessage()
            );

            return [
                'user_email' => $email,
                'main_account_id' => $mainAccountId,
                'browser_notifications_enabled' => 0,
                'notification_sound_enabled' => 0,
                'permission_prompt_seen' => 0
            ];
        }
    }
}


/* ============================================================
 * SAVE NOTIFICATION PREFERENCES
 * ============================================================ */

if (!function_exists('saveNotificationPreferences')) {
    function saveNotificationPreferences(
        PDO $pdo,
        string $email,
        int $mainAccountId,
        ?int $browserEnabled = null,
        ?int $soundEnabled = null,
        ?int $promptSeen = null
    ): bool {

        $email = strtolower(trim($email));

        try {

            $current = getNotificationPreferences(
                $pdo,
                $email,
                $mainAccountId
            );

            $browser = $browserEnabled === null
                ? (int)($current['browser_notifications_enabled'] ?? 0)
                : ($browserEnabled ? 1 : 0);

            $sound = $soundEnabled === null
                ? (int)($current['notification_sound_enabled'] ?? 0)
                : ($soundEnabled ? 1 : 0);

            $seen = $promptSeen === null
                ? (int)($current['permission_prompt_seen'] ?? 0)
                : ($promptSeen ? 1 : 0);

            $u = $pdo->prepare("
                UPDATE notification_preferences
                SET
                    main_account_id = ?,
                    browser_notifications_enabled = ?,
                    notification_sound_enabled = ?,
                    permission_prompt_seen = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE LOWER(user_email) = ?
            ");

            $u->execute([
                $mainAccountId > 0 ? $mainAccountId : null,
                $browser,
                $sound,
                $seen,
                $email
            ]);

            return true;

        } catch (Throwable $e) {

            error_log(
                '[HarvHub Notification Service] saveNotificationPreferences failed: '
                . $e->getMessage()
            );

            return false;
        }
    }
}


/* ============================================================
 * SYNC UNREAD FLAG
 * ============================================================ */

if (!function_exists('syncNotificationUnreadFlag')) {
    function syncNotificationUnreadFlag(
        PDO $pdo,
        string $email,
        int $subAccountId
    ): void {

        if ($subAccountId <= 0) {
            return;
        }

        try {

            $q = $pdo->prepare("
                SELECT COUNT(*)
                FROM contract_notifications
                WHERE LOWER(user_email) = ?
                  AND sub_account_id = ?
                  AND read_at IS NULL
            ");

            $q->execute([
                strtolower(trim($email)),
                $subAccountId
            ]);

            $hasUnread =
                ((int)$q->fetchColumn() > 0)
                    ? 1
                    : 0;

            $u = $pdo->prepare("
                UPDATE harvhub
                SET unread_notifications = ?
                WHERE sub_account_id = ?
                  AND LOWER(email) = ?
            ");

            $u->execute([
                $hasUnread,
                $subAccountId,
                strtolower(trim($email))
            ]);

        } catch (Throwable $e) {

            error_log(
                '[HarvHub Notification Service] syncNotificationUnreadFlag failed: '
                . $e->getMessage()
            );
        }
    }
}


/* ============================================================
 * GET UNREAD COUNT
 * ============================================================ */

if (!function_exists('getNotificationUnreadCount')) {
    function getNotificationUnreadCount(
        PDO $pdo,
        string $email,
        int $subAccountId
    ): int {

        try {

            $q = $pdo->prepare("
                SELECT COUNT(*)
                FROM contract_notifications
                WHERE LOWER(user_email) = ?
                  AND sub_account_id = ?
                  AND read_at IS NULL
            ");

            $q->execute([
                strtolower(trim($email)),
                $subAccountId
            ]);

            return (int)$q->fetchColumn();

        } catch (Throwable $e) {

            error_log(
                '[HarvHub Notification Service] getNotificationUnreadCount failed: '
                . $e->getMessage()
            );

            return 0;
        }
    }
}


/* ============================================================
 * GET CONTRACT NOTIFICATIONS
 * ============================================================ */

if (!function_exists('getContractNotifications')) {
    function getContractNotifications(
        PDO $pdo,
        string $email,
        int $subAccountId,
        int $limit = 100
    ): array {

        $limit = max(
            1,
            min(200, $limit)
        );

        try {

            $q = $pdo->prepare("
                SELECT
                    id,
                    notification_key,
                    title,
                    message,
                    type,
                    section,
                    action_tab,
                    read_at,
                    created_at
                FROM contract_notifications
                WHERE LOWER(user_email) = ?
                  AND sub_account_id = ?
                ORDER BY created_at DESC, id DESC
                LIMIT {$limit}
            ");

            $q->execute([
                strtolower(trim($email)),
                $subAccountId
            ]);

            $rows = $q->fetchAll(PDO::FETCH_ASSOC);

            $out = [];

            foreach ($rows as $row) {

                $out[] = [
                    'id' => (int)$row['id'],

                    'notification_key' =>
                        (string)$row['notification_key'],

                    'title' =>
                        (string)$row['title'],

                    'section' =>
                        (string)(
                            $row['section']
                            ?: 'General'
                        ),

                    'message' =>
                        harvhubNotificationCleanText(
                            $row['message']
                        ),

                    'time' =>
                        $row['created_at'],

                    'type' =>
                        (string)(
                            $row['type']
                            ?: 'info'
                        ),

                    'action_tab' =>
                        $row['action_tab'],

                    'update' =>
                        empty($row['read_at'])
                            ? 'new'
                            : 'read'
                ];
            }

            return $out;

        } catch (Throwable $e) {

            error_log(
                '[HarvHub Notification Service] getContractNotifications failed: '
                . $e->getMessage()
            );

            return [];
        }
    }
}


/* ============================================================
 * MARK NOTIFICATIONS READ
 * ============================================================ */

if (!function_exists('markContractNotificationsRead')) {
    function markContractNotificationsRead(
        PDO $pdo,
        string $email,
        int $subAccountId
    ): bool {

        try {

            $email = strtolower(trim($email));

            $u = $pdo->prepare("
                UPDATE contract_notifications
                SET read_at = COALESCE(
                    read_at,
                    CURRENT_TIMESTAMP
                )
                WHERE LOWER(user_email) = ?
                  AND sub_account_id = ?
                  AND read_at IS NULL
            ");

            $u->execute([
                $email,
                $subAccountId
            ]);

            $u2 = $pdo->prepare("
                UPDATE harvhub
                SET unread_notifications = 0
                WHERE LOWER(email) = ?
                  AND sub_account_id = ?
            ");

            $u2->execute([
                $email,
                $subAccountId
            ]);

            return true;

        } catch (Throwable $e) {

            error_log(
                '[HarvHub Notification Service] markContractNotificationsRead failed: '
                . $e->getMessage()
            );

            return false;
        }
    }
}


/* ============================================================
 * RECORD CONTRACT NOTIFICATION
 * ============================================================ */

if (!function_exists('recordContractNotification')) {

    /**
     * Record a notification.
     *
     * For state/required notifications:
     *   $force = false
     *
     * The notification is inserted only when the latest notification
     * for that account has a different notification_key.
     *
     * For actions/events:
     *   $force = true
     *
     * Every action is recorded immediately.
     *
     * IMPORTANT:
     * Email is sent AFTER the DB transaction commits.
     * A mail failure must never remove the in-app notification.
     */
    function recordContractNotification(
        PDO $pdo,
        array $payload
    ): ?int {

        $email = strtolower(
            trim(
                (string)(
                    $payload['user_email'] ?? ''
                )
            )
        );

        $subId = (int)(
            $payload['sub_account_id'] ?? 0
        );

        $mainId = (int)(
            $payload['main_account_id'] ?? 0
        );

        $key = trim(
            (string)(
                $payload['notification_key'] ?? ''
            )
        );

        $title = trim(
            (string)(
                $payload['title'] ?? 'Notification'
            )
        );

        $message = harvhubNotificationCleanText(
            $payload['message'] ?? ''
        );

        $type = trim(
            (string)(
                $payload['type'] ?? 'info'
            )
        ) ?: 'info';

        $section = trim(
            (string)(
                $payload['section'] ?? 'General'
            )
        ) ?: 'General';

        $actionTab =
            isset($payload['action_tab'])
                ? trim(
                    (string)$payload['action_tab']
                )
                : null;

        $force = !empty(
            $payload['force']
        );

        if (
            $email === '' ||
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            ) ||
            $subId <= 0 ||
            $key === '' ||
            $message === ''
        ) {

            error_log(
                '[HarvHub Notification Service] Invalid notification payload.'
            );

            return null;
        }

        try {

            /* ------------------------------------------------
             * DEDUPLICATION
             * ------------------------------------------------ */

            if (!$force) {

                $q = $pdo->prepare("
                    SELECT notification_key
                    FROM contract_notifications
                    WHERE LOWER(user_email) = ?
                      AND sub_account_id = ?
                    ORDER BY created_at DESC, id DESC
                    LIMIT 1
                ");

                $q->execute([
                    $email,
                    $subId
                ]);

                $latestKey =
                    (string)(
                        $q->fetchColumn()
                        ?: ''
                    );

                if ($latestKey === $key) {
                    return null;
                }
            }


            /* ------------------------------------------------
             * START TRANSACTION
             * ------------------------------------------------ */

            $pdo->beginTransaction();


            /* ------------------------------------------------
             * INSERT NOTIFICATION
             * ------------------------------------------------ */

            $ins = $pdo->prepare("
                INSERT INTO contract_notifications
                (
                    user_email,
                    sub_account_id,
                    main_account_id,
                    notification_key,
                    title,
                    message,
                    type,
                    section,
                    action_tab
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $ins->execute([
                $email,

                $subId,

                $mainId > 0
                    ? $mainId
                    : 0,

                substr(
                    $key,
                    0,
                    64
                ),

                substr(
                    $title,
                    0,
                    255
                ),

                $message,

                substr(
                    $type,
                    0,
                    32
                ),

                substr(
                    $section,
                    0,
                    64
                ),

                $actionTab !== ''
                    ? substr(
                        $actionTab,
                        0,
                        64
                    )
                    : null
            ]);


            $id = (int)$pdo->lastInsertId();


            /* ------------------------------------------------
             * SET UNREAD FLAG
             * ------------------------------------------------ */

            $flag = $pdo->prepare("
                UPDATE harvhub
                SET unread_notifications = 1
                WHERE LOWER(email) = ?
                  AND sub_account_id = ?
            ");

            $flag->execute([
                $email,
                $subId
            ]);


            /* ------------------------------------------------
             * COMMIT DATABASE TRANSACTION
             * ------------------------------------------------ */

            $pdo->commit();


            /* =================================================
             * EMAIL NOTIFICATION
             *
             * IMPORTANT:
             * This uses notification_email.php.
             *
             * notification_email.php MUST use:
             *
             * server_account.mailer_email
             * server_account.mailer_password
             *
             * and:
             *
             * https://api.brevo.com/v3/smtp/email
             *
             * with mailer_password as the Brevo API key.
             * ================================================= */

            try {

                require_once __DIR__ . '/notification_email.php';


                /* ---------------------------------------------
                 * Resolve recipient name.
                 * --------------------------------------------- */

                $recipientName = '';

                try {

                    $nameStmt = $pdo->prepare("
                        SELECT
                            fullname,
                            first_name
                        FROM harvhub
                        WHERE LOWER(email) = ?
                        ORDER BY id ASC
                        LIMIT 1
                    ");

                    $nameStmt->execute([
                        $email
                    ]);

                    $nameRow =
                        $nameStmt->fetch(
                            PDO::FETCH_ASSOC
                        );

                    if ($nameRow) {

                        $recipientName = trim(
                            (string)(
                                $nameRow['first_name']
                                ?? ''
                            )
                        );

                        if ($recipientName === '') {

                            $recipientName = trim(
                                (string)(
                                    $nameRow['fullname']
                                    ?? ''
                                )
                            );
                        }
                    }

                } catch (Throwable $nameError) {

                    error_log(
                        '[HarvHub Notification Service] Recipient name lookup failed'
                        . ' | notification_id=' . $id
                        . ' | error=' . $nameError->getMessage()
                    );
                }


                /* ---------------------------------------------
                 * SEND EMAIL
                 * --------------------------------------------- */

                $mailResult = sendNotificationEmail(
                    $email,
                    $title,
                    $message,
                    [
                        'pdo' =>
                            $pdo,

                        'section' =>
                            $section,

                        'sub_account_id' =>
                            $subId,

                        'notification_id' =>
                            $id,

                        'action_tab' =>
                            $actionTab,

                        'recipient_name' =>
                            $recipientName
                    ]
                );


                /* ---------------------------------------------
                 * EMAIL SUCCESS
                 * --------------------------------------------- */

                if ($mailResult === true) {

                    $em = $pdo->prepare("
                        UPDATE contract_notifications
                        SET
                            email_sent = 1,
                            email_sent_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");

                    $em->execute([
                        $id
                    ]);

                    error_log(
                        '[HarvHub Notification Service] Notification email marked sent'
                        . ' | notification_id=' . $id
                        . ' | recipient=' . $email
                    );

                } else {

                    error_log(
                        '[HarvHub Notification Service] Notification email returned FALSE'
                        . ' | notification_id=' . $id
                        . ' | recipient=' . $email
                    );
                }


            } catch (Throwable $mailError) {

                /*
                 * DO NOT roll back the notification.
                 *
                 * The notification was already committed.
                 *
                 * email_sent intentionally remains 0.
                 */

                error_log(
                    '[HarvHub Notification Service] Notification email exception'
                    . ' | notification_id=' . $id
                    . ' | recipient=' . $email
                    . ' | error=' . $mailError->getMessage()
                    . ' | file=' . $mailError->getFile()
                    . ' | line=' . $mailError->getLine()
                );
            }


            /* ------------------------------------------------
             * Return notification ID regardless of email result.
             * ------------------------------------------------ */

            return $id;


        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {

                try {
                    $pdo->rollBack();
                } catch (Throwable $ignored) {
                }
            }

            error_log(
                '[HarvHub Notification Service] Failed to record notification'
                . ' | email=' . $email
                . ' | error=' . $e->getMessage()
                . ' | file=' . $e->getFile()
                . ' | line=' . $e->getLine()
            );

            return null;
        }
    }
}


/* ============================================================
 * RECORD CONTRACT NOTIFICATION ONCE
 * ============================================================ */

if (!function_exists('recordContractNotificationOnce')) {

    /**
     * Insert an event only once for the same exact notification key.
     *
     * Useful for payment status events whose status can remain unchanged
     * across many dashboard refreshes.
     */
    function recordContractNotificationOnce(
        PDO $pdo,
        array $payload
    ): ?int {

        $email = strtolower(
            trim(
                (string)(
                    $payload['user_email'] ?? ''
                )
            )
        );

        $subId = (int)(
            $payload['sub_account_id'] ?? 0
        );

        $key = trim(
            (string)(
                $payload['notification_key'] ?? ''
            )
        );

        if (
            $email === '' ||
            $subId <= 0 ||
            $key === ''
        ) {
            return null;
        }

        try {

            $q = $pdo->prepare("
                SELECT id
                FROM contract_notifications
                WHERE LOWER(user_email) = ?
                  AND sub_account_id = ?
                  AND notification_key = ?
                ORDER BY id DESC
                LIMIT 1
            ");

            $q->execute([
                $email,
                $subId,
                substr(
                    $key,
                    0,
                    64
                )
            ]);

            if ($q->fetchColumn()) {
                return null;
            }

        } catch (Throwable $e) {

            error_log(
                '[HarvHub Notification Service] Notification-once lookup failed: '
                . $e->getMessage()
            );
        }

        $payload['force'] = true;

        return recordContractNotification(
            $pdo,
            $payload
        );
    }
}


/* ============================================================
 * SYNC DASHBOARD CONTRACT NOTIFICATIONS
 * ============================================================ */

if (!function_exists('syncDashboardContractNotifications')) {

    /**
     * Record the current actionable dashboard/session state.
     *
     * Only the highest-priority current state is recorded.
     *
     * This mirrors the existing dashboard/session-modal priority tree
     * and prevents one page refresh from creating several competing
     * notifications.
     */
    function syncDashboardContractNotifications(
        PDO $pdo,
        array $user,
        $latestRevenueRecord,
        bool $userHasVps,
        bool $hasProgramme,
        bool $brokerConnected,
        bool $isContractActive,
        bool $contractCompleted,
        float $brokerBalance,
        float $minDeposit,
        string $balanceVerificationStatus,
        int $mainAccountId,
        int $subAccountId
    ): ?int {

        $email = strtolower(
            trim(
                (string)(
                    $user['email'] ?? ''
                )
            )
        );

        if (
            $email === '' ||
            $subAccountId <= 0
        ) {
            return null;
        }


        /* ------------------------------------------------
         * PAYMENT STATUSES
         * ------------------------------------------------ */

        $statuses = [];

        $loyalty =
            $user['loyalties'] ?? null;

        if (
            $loyalty !== null &&
            $loyalty !== ''
        ) {
            $statuses[] = $loyalty;
        }

        if (
            $latestRevenueRecord &&
            isset(
                $latestRevenueRecord['loyalties']
            ) &&
            $latestRevenueRecord['loyalties'] !== null
        ) {

            $statuses[] =
                $latestRevenueRecord['loyalties'];
        }

        $statuses =
            array_values(
                array_unique(
                    $statuses
                )
            );


        /* ------------------------------------------------
         * PAYMENT EVENT IDENTIFIER
         * ------------------------------------------------ */

        $paymentEventId =
            (int)(
                $latestRevenueRecord['id'] ?? 0
            );

        $paymentKeySuffix =
            $paymentEventId > 0
                ? (string)$paymentEventId
                : substr(
                    sha1(
                        ($user['contract_id'] ?? '')
                        . '|'
                        . $subAccountId
                    ),
                    0,
                    12
                );


        /* ------------------------------------------------
         * PAYMENT EVENT MAP
         * ------------------------------------------------ */

        $paymentEventMap = [

            'payment-made' => [
                'title' =>
                    'Payment Made',

                'message' =>
                    'Your payment has been recorded and is waiting for confirmation.',

                'type' =>
                    'warning'
            ],

            'contract-cancelled-payment-made' => [
                'title' =>
                    'Payment Made',

                'message' =>
                    'Your contract-related payment has been recorded and is waiting for confirmation.',

                'type' =>
                    'warning'
            ],

            'payment-failed' => [
                'title' =>
                    'Payment Failed',

                'message' =>
                    'Your payment attempt was not successful.',

                'type' =>
                    'danger'
            ],

            'failed-payment' => [
                'title' =>
                    'Payment Failed',

                'message' =>
                    'Your payment attempt was not successful.',

                'type' =>
                    'danger'
            ],

            'contract-cancelled-failed-payment' => [
                'title' =>
                    'Payment Failed',

                'message' =>
                    'Your contract-related payment attempt was not successful.',

                'type' =>
                    'danger'
            ],

            'contract-cancelled-payment-failed' => [
                'title' =>
                    'Payment Failed',

                'message' =>
                    'Your contract-related payment attempt was not successful.',

                'type' =>
                    'danger'
            ],

            'unpaid-payment' => [
                'title' =>
                    'Payment Required',

                'message' =>
                    'A payment is required before you can continue.',

                'type' =>
                    'warning'
            ],

            'unpaid' => [
                'title' =>
                    'Payment Required',

                'message' =>
                    'A payment is required before you can continue.',

                'type' =>
                    'warning'
            ],

            'contract-cancelled-unpaid' => [
                'title' =>
                    'Payment Required',

                'message' =>
                    'A payment is required before you can continue.',

                'type' =>
                    'warning'
            ],

            'contract-cancelled-unpaid-payment' => [
                'title' =>
                    'Payment Required',

                'message' =>
                    'A payment is required before you can continue.',

                'type' =>
                    'warning'
            ],

            'contract-cancelled-payment-required' => [
                'title' =>
                    'Payment Required',

                'message' =>
                    'A payment is required before you can continue.',

                'type' =>
                    'warning'
            ],

            'payment-confirmed' => [
                'title' =>
                    'Payment Confirmed',

                'message' =>
                    'Your payment has been confirmed. You can proceed with the next contract step.',

                'type' =>
                    'success'
            ]
        ];


        /* ------------------------------------------------
         * RECORD PAYMENT EVENTS
         * ------------------------------------------------ */

        foreach ($statuses as $paymentStatus) {

            if (
                !isset(
                    $paymentEventMap[
                        $paymentStatus
                    ]
                )
            ) {
                continue;
            }

            $event =
                $paymentEventMap[
                    $paymentStatus
                ];

            recordContractNotificationOnce(
                $pdo,
                [
                    'user_email' =>
                        $email,

                    'sub_account_id' =>
                        $subAccountId,

                    'main_account_id' =>
                        $mainAccountId,

                    'notification_key' =>
                        'payment-event-'
                        . $paymentStatus
                        . '-'
                        . $paymentKeySuffix,

                    'title' =>
                        $event['title'],

                    'message' =>
                        $event['message'],

                    'type' =>
                        $event['type'],

                    'section' =>
                        'Payment',

                    'action_tab' =>
                        'profit_split'
                ]
            );
        }


        /* ------------------------------------------------
         * DETERMINE PAYMENT STATE
         * ------------------------------------------------ */

        $paymentConfirmed =
            in_array(
                'payment-confirmed',
                $statuses,
                true
            );

        $paymentPending = false;
        $paymentFailed = false;
        $paymentUnpaid = false;


        foreach ($statuses as $status) {

            if (
                in_array(
                    $status,
                    [
                        'payment-made',
                        'contract-cancelled-payment-made'
                    ],
                    true
                )
            ) {

                $paymentPending = true;

                break;
            }
        }


        if (!$paymentPending) {

            foreach ($statuses as $status) {

                if (
                    in_array(
                        $status,
                        [
                            'payment-failed',
                            'failed-payment',
                            'contract-cancelled-failed-payment',
                            'contract-cancelled-payment-failed'
                        ],
                        true
                    )
                ) {

                    $paymentFailed = true;

                    break;
                }
            }
        }


        if (
            !$paymentPending &&
            !$paymentFailed &&
            !$paymentConfirmed
        ) {

            foreach ($statuses as $status) {

                if (
                    in_array(
                        $status,
                        [
                            'unpaid-payment',
                            'unpaid',
                            'contract-cancelled-unpaid',
                            'contract-cancelled-unpaid-payment',
                            'contract-cancelled-payment-required'
                        ],
                        true
                    )
                ) {

                    $paymentUnpaid = true;

                    break;
                }
            }
        }


        /* ------------------------------------------------
         * HIGHEST-PRIORITY DASHBOARD STATE
         * ------------------------------------------------ */

        $payload = null;


        if ($paymentPending) {

            $payload = [

                'notification_key' =>
                    'payment-made-pending-confirmation',

                'title' =>
                    'Payment Pending Confirmation',

                'message' =>
                    'Your payment has been recorded and is waiting for confirmation.',

                'type' =>
                    'warning',

                'section' =>
                    'Payment',

                'action_tab' =>
                    'mydashboard'
            ];

        } elseif ($paymentFailed) {

            $payload = [

                'notification_key' =>
                    'payment-failed',

                'title' =>
                    'Payment Failed',

                'message' =>
                    'Your payment attempt was not successful. Please review the payment step and try again.',

                'type' =>
                    'danger',

                'section' =>
                    'Payment',

                'action_tab' =>
                    'profit_split'
            ];

        } elseif ($paymentUnpaid) {

            $payload = [

                'notification_key' =>
                    'unpaid-payment',

                'title' =>
                    'Payment Required',

                'message' =>
                    'A payment is required before you can continue with the next contract step.',

                'type' =>
                    'warning',

                'section' =>
                    'Payment',

                'action_tab' =>
                    'profit_split'
            ];

        } elseif (!$userHasVps) {

            $payload = [

                'notification_key' =>
                    'vps-required',

                'title' =>
                    'VPS Required',

                'message' =>
                    'A VPS is required before you can connect your broker and continue.',

                'type' =>
                    'info',

                'section' =>
                    'VPS',

                'action_tab' =>
                    'vps'
            ];

        } elseif (!$brokerConnected) {

            $payload = [

                'notification_key' =>
                    'broker-details-required',

                'title' =>
                    'Broker Details Required',

                'message' =>
                    'Connect your broker details to continue with your programme.',

                'type' =>
                    'info',

                'section' =>
                    'Broker',

                'action_tab' =>
                    'connect_investor_broker'
            ];

        } elseif (!$hasProgramme) {

            $payload = [

                'notification_key' =>
                    'programme-exploration-required',

                'title' =>
                    'Explore a Programme',

                'message' =>
                    'You have not joined a programme yet. Explore available programmes to continue.',

                'type' =>
                    'info',

                'section' =>
                    'Programme',

                'action_tab' =>
                    'programmes'
            ];

        } elseif (
            (int)(
                $user['reset_contract'] ?? 0
            ) === 1
        ) {

            $payload = [

                'notification_key' =>
                    'contract-reset-required',

                'title' =>
                    'Verification Required',

                'message' =>
                    'Your previous contract has been reset. Apply for verification to begin a new contract.',

                'type' =>
                    'warning',

                'section' =>
                    'Contract',

                'action_tab' =>
                    'mydashboard'
            ];

        } elseif (
            $balanceVerificationStatus
            === 'applied-for-verification'
        ) {

            $payload = [

                'notification_key' =>
                    'verification-applied-waiting-confirmation',

                'title' =>
                    'Verification Under Review',

                'message' =>
                    'Your balance verification application is waiting for confirmation.',

                'type' =>
                    'info',

                'section' =>
                    'Verification',

                'action_tab' =>
                    'mydashboard'
            ];

        } elseif (
            $balanceVerificationStatus
            === 'not-verified' ||
            $balanceVerificationStatus === ''
        ) {

            $payload = [

                'notification_key' =>
                    'apply-for-verification-required',

                'title' =>
                    'Apply for Verification',

                'message' =>
                    'Apply for balance verification once your broker account has the required deposit.',

                'type' =>
                    'info',

                'section' =>
                    'Verification',

                'action_tab' =>
                    'mydashboard'
            ];

        } elseif (
            $balanceVerificationStatus === 'verified' &&
            $brokerBalance < $minDeposit
        ) {

            $payload = [

                'notification_key' =>
                    'deposit-required-balance-below-minimum',

                'title' =>
                    'Deposit Required',

                'message' =>
                    'Your verified broker balance is below the minimum required balance of $'
                    . number_format(
                        $minDeposit,
                        2
                    )
                    . '.',

                'type' =>
                    'warning',

                'section' =>
                    'Balance',

                'action_tab' =>
                    'mydashboard'
            ];

        } elseif (
            $balanceVerificationStatus === 'verified' &&
            $brokerBalance >= $minDeposit &&
            !$isContractActive &&
            !$contractCompleted
        ) {

            $payload = [

                'notification_key' =>
                    'balance-verified-meets-requirement',

                'title' =>
                    'Balance Requirement Met',

                'message' =>
                    'Your balance has been verified and meets the minimum requirement for the contract.',

                'type' =>
                    'success',

                'section' =>
                    'Balance',

                'action_tab' =>
                    'mydashboard'
            ];

        } elseif ($isContractActive) {

            $payload = [

                'notification_key' =>
                    'contract-active',

                'title' =>
                    'Contract is Active',

                'message' =>
                    'Your trading contract is currently active.',

                'type' =>
                    'success',

                'section' =>
                    'Contract',

                'action_tab' =>
                    'mydashboard'
            ];

        } elseif (!$contractCompleted) {

            $payload = [

                'notification_key' =>
                    'enroll-required',

                'title' =>
                    'Ready to Enroll',

                'message' =>
                    'You are ready to enroll in a new trading contract.',

                'type' =>
                    'success',

                'section' =>
                    'Contract',

                'action_tab' =>
                    'mydashboard'
            ];
        }


        if (!$payload) {
            return null;
        }


        /* ------------------------------------------------
         * ADD ACCOUNT DATA
         * ------------------------------------------------ */

        $payload['user_email'] =
            $email;

        $payload['sub_account_id'] =
            $subAccountId;

        $payload['main_account_id'] =
            $mainAccountId;

        $payload['force'] =
            false;


        /* ------------------------------------------------
         * RECORD
         * ------------------------------------------------ */

        return recordContractNotification(
            $pdo,
            $payload
        );
    }
}