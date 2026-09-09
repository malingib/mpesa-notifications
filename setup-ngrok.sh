#!/bin/bash

# Setup script for ngrok webhook testing

echo "🔧 M-Pesa Webhook Setup with ngrok"
echo "===================================="
echo ""

# Check if ngrok is installed
if ! command -v ngrok &> /dev/null; then
    echo "❌ ngrok is not installed!"
    echo ""
    echo "Install ngrok:"
    echo "1. Visit: https://ngrok.com/download"
    echo "2. Or install via package manager"
    echo ""
    exit 1
fi

echo "✅ ngrok found"
echo ""

# Check if Laravel server is running
if ! curl -s http://localhost:8000 > /dev/null 2>&1; then
    echo "⚠️  Laravel server doesn't seem to be running on port 8000"
    echo "   Start it with: php artisan serve"
    echo ""
    read -p "Continue anyway? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

echo "📡 Starting ngrok tunnel..."
echo ""
echo "Your webhook URL will be: https://[ngrok-url]/api/webhooks/mpesa/payment"
echo ""
echo "⚠️  IMPORTANT:"
echo "1. Copy the 'Forwarding' URL from ngrok output"
echo "2. Go to M-Pesa Developer Portal"
echo "3. Set Confirmation URL to: https://[ngrok-url]/api/webhooks/mpesa/payment"
echo "4. Keep this terminal open (ngrok must stay running)"
echo ""
echo "Press Ctrl+C to stop ngrok"
echo ""

# Start ngrok
ngrok http 8000
