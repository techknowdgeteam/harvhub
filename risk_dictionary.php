<?php
// risk_dictionary.php
?>

<h2> Recovery Table Generator</h2>

<div class="risk-dictionary-container">
    <!-- Recovery Table Controller -->
    <div id="recovery-tab" class="risk-tab-content active">
        <div class="management-header">
            <h3>Recovery Table Controller</h3>
        </div>

        <div class="recovery-generator-container">
            <div class="recovery-form-grid">
                <div class="recovery-form-group">
                    <label class="form-label">Risk Reward</label>
                    <input type="text" id="ctrl-risk-reward" class="form-input" placeholder="1:2">
                </div>

                <div class="recovery-form-group">
                    <label class="form-label">Default Risk</label>
                    <input type="number" id="ctrl-default-risk" class="form-input" min="0.01" step="0.01" placeholder="20">
                </div>

                <div class="recovery-form-group">
                    <label class="form-label">Daily Target</label>
                    <input type="number" id="ctrl-daily-target" class="form-input" min="0" step="0.01" placeholder="40">
                </div>

                <div class="recovery-form-group">
                    <label class="form-label"> Factor</label>
                    <select id="ctrl-factor" class="form-select">
                        <option value="takeprofit_factor">Profit Factor</option>
                        <option value="stoploss_factor">Stop Loss Factor</option>
                    </select>
                </div>

                <div class="recovery-form-group">
                    <label class="form-label">Retention %</label>
                    <input type="number" id="ctrl-retention" class="form-input" min="0" max="100" step="1" placeholder="0">
                </div>

                <div class="recovery-form-group">
                    <label class="form-label">Recovery Adder %</label>
                    <input type="number" id="ctrl-recovery-adder" class="form-input" min="0" step="1" placeholder="0">
                </div>

                <div class="recovery-form-group">
                    <label class="form-label">Trade Columns</label>
                    <input type="number" id="ctrl-trade-count" class="form-input" min="1" max="50" step="1" placeholder="10">
                </div>

                <div class="recovery-form-group">
                    <label class="form-label">Sequential Trades</label>
                    <input type="number" id="ctrl-sequential-trades" class="form-input" min="1" max="50" step="1" placeholder="10">
                </div>
            </div>
        </div>

        <div id="recovery-table-container" class="recovery-table-wrapper">
            <div style="text-align: center; padding: 60px 20px; color: #888;">
                <div style="font-size: 48px; margin-bottom: 20px;">Select</div>
                <p style="font-size: 16px;">Adjust the controller values and the table will auto-generate</p>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
// RECOVERY TABLE GENERATOR - FINAL FIXED
// ============================================

function generateRecoveryTable() {
    // Get controller values
    const riskRewardStr = document.getElementById('ctrl-risk-reward').value.trim();
    const defaultRiskInput = document.getElementById('ctrl-default-risk').value.trim();
    const dailyTargetInput = document.getElementById('ctrl-daily-target').value.trim();
    const factor = document.getElementById('ctrl-factor').value;
    const retentionInput = document.getElementById('ctrl-retention').value.trim();
    const recoveryAdderInput = document.getElementById('ctrl-recovery-adder').value.trim();
    const tradeCountInput = document.getElementById('ctrl-trade-count').value.trim();
    const sequentialCountInput = document.getElementById('ctrl-sequential-trades').value.trim();

    // Validate required inputs
    if (!riskRewardStr || !riskRewardStr.includes(':')) {
        showError('Please enter a valid Risk Reward ratio (e.g., 1:2)');
        return;
    }

    if (defaultRiskInput === '') {
        showError('Please enter a Default Risk value');
        return;
    }

    const defaultRisk = parseFloat(defaultRiskInput);
    if (isNaN(defaultRisk) || defaultRisk <= 0) {
        showError('Default Risk must be a positive number');
        return;
    }

    if (dailyTargetInput === '') {
        showError('Please enter a Daily Target value');
        return;
    }

    const dailyTarget = parseFloat(dailyTargetInput);
    if (isNaN(dailyTarget) || dailyTarget < 0) {
        showError('Daily Target must be a number greater than or equal to 0');
        return;
    }

    if (tradeCountInput === '') {
        showError('Please enter Trade Columns value');
        return;
    }

    const tradeCount = parseInt(tradeCountInput);
    if (isNaN(tradeCount) || tradeCount < 1) {
        showError('Trade Columns must be a positive integer');
        return;
    }

    if (sequentialCountInput === '') {
        showError('Please enter Sequential Trades value');
        return;
    }

    const sequentialCount = parseInt(sequentialCountInput);
    if (isNaN(sequentialCount) || sequentialCount < 1) {
        showError('Sequential Trades must be a positive integer');
        return;
    }

    // Parse optional inputs
    const retention = retentionInput === '' ? 0 : parseFloat(retentionInput);
    const recoveryAdder = recoveryAdderInput === '' ? 0 : parseFloat(recoveryAdderInput);

    // Parse risk reward ratio
    const riskRewardParts = riskRewardStr.split(':');
    if (riskRewardParts.length !== 2) {
        showError('Risk Reward must be in format X:Y (e.g., 1:2)');
        return;
    }

    const riskRatio = parseFloat(riskRewardParts[0]);
    const rewardRatio = parseFloat(riskRewardParts[1]);

    if (isNaN(riskRatio) || riskRatio <= 0 || isNaN(rewardRatio) || rewardRatio <= 0) {
        showError('Risk Reward values must be positive numbers');
        return;
    }

    const isTPFactor = factor === 'takeprofit_factor';

    // Generate pure sequence
    const pureSequence = generatePureSequence(defaultRisk, riskRatio, rewardRatio, sequentialCount);
    
    // Generate table data
    const tableData = generateRecoveryTableData(
        defaultRisk, dailyTarget, riskRatio, rewardRatio,
        recoveryAdder, retention, tradeCount, sequentialCount, 
        pureSequence, isTPFactor
    );

    // Render table
    renderRecoveryTable(tableData, defaultRisk, dailyTarget, riskRatio, rewardRatio, 
                       recoveryAdder, retention, pureSequence, isTPFactor);
}

function generatePureSequence(defaultRisk, riskRatio, rewardRatio, sequentialCount) {
    const sequence = [];
    let totalLost = 0;
    
    for (let i = 0; i < sequentialCount; i++) {
        let riskAmount;
        
        if (i === 0) {
            // First trade: just use default risk
            riskAmount = defaultRisk;
        } else {
            // All previous trades are assumed lost
            // Need to risk enough to recover ALL losses + default risk
            riskAmount = (totalLost + defaultRisk) * (riskRatio / rewardRatio);
            
            // Ensure we never go below default risk
            if (riskAmount < defaultRisk) {
                riskAmount = defaultRisk;
            }
        }
        
        // Round to 2 decimal places
        riskAmount = Math.round(riskAmount * 100) / 100;
        sequence.push(riskAmount);
        
        // Add this trade to total lost (assuming it loses)
        totalLost += riskAmount;
    }
    
    return sequence;
}

function generateRecoveryTableData(defaultRisk, dailyTarget, riskRatio, rewardRatio, 
                                  recoveryAdder, retention, tradeCount, sequentialCount, 
                                  pureSequence, isTPFactor) {
    const data = [];
    
    // Track owed target across trade columns
    let accumulatedOwed = 0;
    
    // For each trade column
    for (let i = 0; i < tradeCount; i++) {
        // Get source value from pure sequence at same index
        const sourceIndex = i % pureSequence.length;
        const sourceValue = pureSequence[sourceIndex];
        
        // Current owed target is sum of all previous daily targets (assuming they failed)
        const currentOwed = accumulatedOwed;
        
        // Generate sequential trades for this column
        const sequence = [];
        let totalLost = 0; // Losses within this column's sequential trades
        
        for (let j = 0; j < sequentialCount; j++) {
            // Calculate retention amount based on default risk
            const retentionAmount = (retention / 100) * defaultRisk;
            
            // Base required amount: lost (within column) + owed (from previous columns) + daily target + retention
            let requiredAmount = totalLost + currentOwed + dailyTarget + retentionAmount;
            
            // Apply recovery adder % if > 0
            if (recoveryAdder > 0) {
                requiredAmount = requiredAmount * (1 + (recoveryAdder / 100));
            }
            
            // Calculate risk amount based on risk reward ratio
            let riskAmount = requiredAmount * (riskRatio / rewardRatio);
            
            // CRITICAL FIX: Ensure risk amount never goes below source value
            if (riskAmount < sourceValue) {
                riskAmount = sourceValue;
            }
            
            // Round to 2 decimal places
            riskAmount = Math.round(riskAmount * 100) / 100;
            
            // Store the trade data
            sequence.push({
                riskAmount: riskAmount,
                sourceValue: sourceValue,
                owedTarget: currentOwed,
                dailyTarget: dailyTarget,
                totalTarget: requiredAmount,
                retentionAmount: retentionAmount,
                adderApplied: recoveryAdder > 0,
                adderValue: recoveryAdder > 0 ? (requiredAmount * recoveryAdder / 100) : 0
            });
            
            // If this trade loses, add it to total lost within this column
            totalLost += riskAmount;
        }
        
        // Calculate risk cap (sum of all risk amounts in sequence)
        const riskCap = sequence.reduce((sum, trade) => sum + trade.riskAmount, 0);
        
        data.push({
            tradeNumber: i + 1,
            sequence: sequence,
            riskCap: riskCap,
            sourceValue: sourceValue,
            owedTarget: currentOwed
        });
        
        // After this column, add its daily target to accumulated owed for next column
        accumulatedOwed += dailyTarget;
    }
    
    return data;
}

function formatSequenceWithChunks(sequence, chunkSize) {
    const chunks = [];
    for (let i = 0; i < sequence.length; i += chunkSize) {
        chunks.push(sequence.slice(i, i + chunkSize));
    }
    return chunks.map(chunk => chunk.join(', ')).join(',<br>');
}

function renderRecoveryTable(data, defaultRisk, dailyTarget, riskRatio, rewardRatio, 
                            recoveryAdder, retention, pureSequence, isTPFactor) {
    const container = document.getElementById('recovery-table-container');

    if (!container || data.length === 0) {
        container.innerHTML = '<div style="text-align: center; padding: 40px; color: #888;">No data to display</div>';
        return;
    }

    const totalRiskCap = data.reduce((sum, col) => sum + col.riskCap, 0);
    const chunkSize = 3;

    // Build header row with trade columns
    let headerHtml = '<tr><th>Risk</th><th>Risk Reward</th><th>Pure Sequence</th>';
    for (let i = 0; i < data.length; i++) {
        headerHtml += `<th>Trade ${i + 1}</th>`;
    }
    headerHtml += '</tr>';

    // Build the table rows
    let bodyHtml = '';

    bodyHtml += '<tr>';
    bodyHtml += `<td>$${defaultRisk.toFixed(2)}</td>`;
    bodyHtml += `<td>${riskRatio}:${rewardRatio}</td>`;
    
    // Pure sequence column
    const pureSeqValues = pureSequence.map(v => v.toFixed(2));
    const formattedPureSeq = formatSequenceWithChunks(pureSeqValues, chunkSize);
    bodyHtml += `
        <td class="trade-cell">
            <div class="trade-data">
                <div class="trade-row"><span class="trade-label">Pure Sequence:</span></div>
                <div class="trade-sequence">(${formattedPureSeq})</div>
                <div class="trade-row"><span class="trade-label">Total Pure Loss:</span> <span class="trade-value">$${pureSequence.reduce((a,b) => a+b, 0).toFixed(2)}</span></div>
            </div>
        </td>
    `;
    
    // Trade columns
    for (let i = 0; i < data.length; i++) {
        const seqValues = data[i].sequence.map(trade => trade.riskAmount.toFixed(2));
        const formattedSeq = formatSequenceWithChunks(seqValues, chunkSize);
        
        // Get first trade data for display
        const firstTrade = data[i].sequence[0];
        const columnOwed = data[i].owedTarget;
        
        bodyHtml += `
            <td class="trade-cell">
                <div class="trade-data">
                    <div class="trade-row"><span class="trade-label">Source:</span> <span class="trade-value">$${data[i].sourceValue.toFixed(2)}</span></div>
                    <div class="trade-row"><span class="trade-label">Owed Target:</span> <span class="trade-value">$${columnOwed.toFixed(2)}</span></div>
                    <div class="trade-row"><span class="trade-label">Daily Target:</span> <span class="trade-value">$${dailyTarget.toFixed(2)}</span></div>
                    <div class="trade-row"><span class="trade-label">Total Target:</span> <span class="trade-value">$${firstTrade.totalTarget.toFixed(2)}</span></div>
                    <div class="trade-row"><span class="trade-label">Retention ${retention}%:</span> <span class="trade-value">$${firstTrade.retentionAmount.toFixed(2)}</span></div>
                    ${firstTrade.adderApplied ? `<div class="trade-row"><span class="trade-label">Adder ${recoveryAdder}%:</span> <span class="trade-value">$${firstTrade.adderValue.toFixed(2)}</span></div>` : ''}
                    <div class="trade-row"><span class="trade-label">Sequential Trades:</span></div>
                    <div class="trade-sequence">(${formattedSeq})</div>
                    <div class="trade-row"><span class="trade-label">Risk Cap:</span> <span class="trade-value risk-cap-value">$${data[i].riskCap.toFixed(2)}</span></div>
                </div>
            </td>
        `;
    }
    bodyHtml += '</tr>';

    // Build the complete table
    let html = `
        <div class="table-responsive">
            <table class="recovery-table">
                <thead>
                    ${headerHtml}
                </thead>
                <tbody>
                    ${bodyHtml}
                </tbody>
            </table>
        </div>
        <div style="padding: 15px 20px; background: var(--bg-tertiary); border-top: 1px solid var(--border-color); font-size: 13px; color: #888;">
        </div>
    `;

    container.innerHTML = html;
}

function showError(message) {
    const container = document.getElementById('recovery-table-container');
    container.innerHTML = `
        <div style="text-align: center; padding: 60px 20px; color: #e74c3c;">
            <p style="font-size: 16px;">${message}</p>
        </div>
    `;
}

// Auto-generate on page load and on input changes
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('#ctrl-risk-reward, #ctrl-default-risk, #ctrl-daily-target, #ctrl-factor, #ctrl-retention, #ctrl-recovery-adder, #ctrl-trade-count, #ctrl-sequential-trades');

    inputs.forEach(input => {
        input.addEventListener('change', generateRecoveryTable);
        input.addEventListener('blur', generateRecoveryTable);
        input.addEventListener('input', function(e) {
            if (e.target.id === 'ctrl-risk-reward' || e.target.id === 'ctrl-default-risk' || 
                e.target.id === 'ctrl-daily-target' || e.target.id === 'ctrl-trade-count' || 
                e.target.id === 'ctrl-sequential-trades') {
                clearTimeout(this.timeout);
                this.timeout = setTimeout(generateRecoveryTable, 300);
            }
        });
        if (input.tagName === 'SELECT') {
            input.addEventListener('change', generateRecoveryTable);
        }
    });

    generateRecoveryTable();
});
</script>