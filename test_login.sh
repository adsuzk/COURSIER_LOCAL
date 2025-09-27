#!/bin/bash
echo "Testing login with CM20250003 credentials..."

# Login attempt
RESPONSE=$(curl -s -w "HTTP_CODE:%{http_code}" \
    -X POST "http://192.168.1.5/COURSIER_LOCAL/api/agent_auth.php" \
    -H "Content-Type: application/json" \
    -d '{"action":"login","identifier":"CM20250003","password":"KOrxI"}')

echo "Raw response: $RESPONSE"

HTTP_CODE=$(echo "$RESPONSE" | grep -o "HTTP_CODE:[0-9]*" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed 's/HTTP_CODE:[0-9]*$//')

echo "HTTP Code: $HTTP_CODE"
echo "Response Body: $BODY"

if [ "$HTTP_CODE" = "200" ] && echo "$BODY" | grep -q '"success":true'; then
    echo "✓ Login SUCCESS"
else
    echo "✗ Login FAILED"
fi