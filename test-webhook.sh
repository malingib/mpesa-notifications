#!/bin/bash

# Test webhook endpoint with sample Till payment
# Replace YOUR_TILL_NUMBER with your actual till number

TILL_NUMBER="8556534"
BASE_URL="http://localhost:8000"

echo "Testing webhook endpoint: ${BASE_URL}/api/webhooks/mpesa/payment"
echo "Till Number: ${TILL_NUMBER}"
echo ""

curl -X POST "${BASE_URL}/api/webhooks/mpesa/payment" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "TransactionID": "TEST'$(date +%s)'",
    "Amount": 100,
    "PhoneNumber": "254712295880",
    "TransactionTime": "'$(date +%Y%m%d%H%M%S)'",
    "TillNumber": "'${TILL_NUMBER}'",
    "ReceiptNumber": "RCP'$(date +%s)'",
    "FirstName": "John",
    "LastName": "Doe"
  }' \
  -v

echo ""
echo ""
echo "Check logs: tail -f storage/logs/laravel.log"
