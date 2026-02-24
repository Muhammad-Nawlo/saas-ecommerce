# Production environment checklist

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL` set to production URL
- [ ] `DB_CONNECTION` / central DB credentials (for landlord)
- [ ] `CACHE_DRIVER=redis`
- [ ] `QUEUE_CONNECTION=redis`
- [ ] `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT`
- [ ] `STRIPE_SECRET_KEY` (landlord billing)
- [ ] `STRIPE_WEBHOOK_SECRET` (for billing webhook)
- [ ] `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET` (if S3)
- [ ] `SESSION_DRIVER=redis` or `file`
- [ ] `LOG_CHANNEL=stack` and log level appropriate for production
