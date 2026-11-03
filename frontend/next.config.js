/** @type {import('next').NextConfig} */
const path = require('path');
const nextConfig = {
  webpack(config) {
    config.resolve.alias['@'] = path.join(__dirname, 'src');
    return config;
  },
  async redirects() {
    return [{ source: '/', destination: '/login', permanent: false }];
  },
};
module.exports = nextConfig;
