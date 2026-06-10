import ccxt
exchange = ccxt.kucoin({'enableRateLimit': True})
try:
    currencies = exchange.fetch_currencies()  # Directly tests the failing call
    print("Success! Fetched", len(currencies), "currencies")
    print("Sample:", list(currencies.keys())[:3])  # e.g., ['BTC', 'ETH', 'USDT']
except Exception as e:
    print("Still failing:", str(e))