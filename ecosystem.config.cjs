module.exports = {
  apps: [
    {
      name: 'beyondtechworld-api',
      cwd: '/var/www/beyondtechworld/apps/api',
      script: 'src/main.js',
      instances: 1,
      autorestart: true,
      max_memory_restart: '300M',
      env: {
        NODE_ENV: 'production',
      },
    },
  ],
};
