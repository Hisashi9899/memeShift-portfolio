/** @type {import('next').NextConfig} */
const path = require('path');

const demo = process.env.NEXT_PUBLIC_DEMO_MODE === '1';
const basePath = process.env.NEXT_PUBLIC_BASE_PATH || '';

const nextConfig = {
  webpack(config) {
    config.resolve.alias['@'] = path.join(__dirname, 'src');
    return config;
  },
  ...(demo
    ? {
        // Static, client-side-only build for GitHub Pages.
        output: 'export',
        trailingSlash: true,
        basePath: basePath || undefined,
        assetPrefix: basePath || undefined,
        images: { unoptimized: true },
      }
    : {
        async redirects() {
          return [{ source: '/', destination: '/login', permanent: false }];
        },
      }),
};

module.exports = nextConfig;
