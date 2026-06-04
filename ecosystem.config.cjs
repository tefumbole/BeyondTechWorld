module.exports = {
  apps: [
    {
      name: 'alphabridge-api',
      cwd: '/var/www/alphabridge/apps/api',
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
