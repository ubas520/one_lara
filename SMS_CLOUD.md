# SMS cloud backend

Existing UI markup, labels, and CSS are unchanged. SMS uses the SMSGate public cloud and the Android device in sms_cloud.local.php (Git-ignored). SMS_CLOUD_USERNAME, SMS_CLOUD_PASSWORD, and SMS_CLOUD_DEVICE_ID environment variables override local values. Keep the namespace stable: it identifies delivery attempts.

New messages send at request shutdown after application transactions commit. Retry creates a new attempt only for failed messages. Opening the gateway reconciles cloud delivery status and fetches cloud devices. Pending/Processed remain "sending"; Sent/Delivered become "sent". Credentials are never rendered.

Optional scheduled status reconciliation:
C:\xampp\php\php.exe C:\xampp\htdocs\onelara\bin\sms_cloud_worker.php

To intentionally drain queued messages (25 per invocation):
C:\xampp\php\php.exe C:\xampp\htdocs\onelara\bin\sms_cloud_worker.php --send-queued

The worker can be scheduled every minute in Windows Task Scheduler. No task is installed automatically and setup does not send old messages. IoT endpoints now return HTTP 410. Old IoT "sending" rows require manual review before requeuing to avoid duplicates.

Timeouts and ambiguous responses remain "sending" and are checked with the same provider ID. They are never automatically resent. For persistent unknown/404 statuses, verify the SMSGate dashboard before manually resetting the row. A crash immediately after claiming can require this review. If shutdown cannot run, its row stays queued; process it with --send-queued.

Requires PHP 8+, cURL, PDO MySQL, a running application database, and outbound HTTPS with a trusted CA bundle. Keep Android Cloud Server running with Internet access and an SMS-capable SIM.

API references:
https://docs.sms-gate.app/getting-started/public-cloud-server/
https://docs.sms-gate.app/features/status-tracking/