module.exports = {
  apps: [
    {
      name: 'beyondtechworld-whatsapp-queue',
      cwd: '/var/www/beyondtechworld/laravel-app',
      script: 'artisan',
      interpreter: 'php',
      args: 'queue:work database --queue=whatsapp,default --sleep=1 --tries=3 --timeout=90',
      instances: 1,
      autorestart: true,
      max_memory_restart: '200M',
      watch: false,
    },
  ],
};
