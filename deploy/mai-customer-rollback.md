# Whats MAI customer service — deploy notes

This does not change the WaSender connection for +237675321739.

## Flags

Leave these off until one new customer is checked by hand:

- `CLOUD_MAI_CUSTOMER=false`
- `CLOUD_MAI_HOST=mai.beyondtechworld.com`
- `CLOUD_WHATSAPP_LINK_URL=http://127.0.0.1:3921`
- `MAI_WORKER_TOKEN=` same value as `storage/app/whatsapp-link/token` (do not commit it)
- `MAI_SESSION_KEY=` a long random string used only to encrypt Baileys credentials (do not commit it)

Do not change `WASENDER_API_KEY`, `WASENDER_SESSION_ID`, or the Wasender webhook secret.

## Migration

Additive only, when you choose to migrate:

```bash
sudo -u www-data php artisan migrate --force --path=database/migrations/2026_11_26_233000_create_mai_customer_sessions.php
```

## Processes

The worker stays on 127.0.0.1:3921.

```bash
pm2 restart beyondtechworld-whatsapp-link
sudo -u www-data php artisan queue:restart
```

When `CLOUD_MAI_CUSTOMER=true`, the scheduler runs `mai:ingest-events` every minute. It only reads local worker files.

## DNS and TLS

`deploy/nginx/mai.beyondtechworld.com.conf` is the vhost. Do not enable it until the certificate exists and the flag is ready.

## Rollback

1. Set `CLOUD_MAI_CUSTOMER=false` and reload PHP-FPM.
2. Customer signup, pairing, and the customer API stop.
3. Do not delete `storage/app/whatsapp-link` and do not restart or log out the WaSender session.
4. The business inbox remains `/admin/whatsapp/chats`.

## Not verified against live WhatsApp

Local tests use a fake link and a fake callback. They do not pair a phone or send a WhatsApp message.
