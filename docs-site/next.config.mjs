/** @type {import('next').NextConfig} */
const isProd = process.env.NODE_ENV === 'production';

const nextConfig = {
  // Static export so the docs can be hosted from any static file
  // host - or opened straight from disk - without a Node server.
  output: 'export',
  images: { unoptimized: true },

  // trailingSlash stays OFF, so routes are flat sibling files
  // (out/index.html, out/guide.html) all at the same depth. Internal
  // navigation uses next/link with canonical routes ("/", "/guide"):
  // that works in `next dev`, on hosts with clean URLs, and via
  // client-side routing after first load. Combined with a relative
  // `assetPrefix`, the JS/CSS bundle resolves whether the export is
  // served from a domain root or nested in a sub-path. (assetPrefix
  // is prod-only; a relative prefix breaks `next dev` HMR.)
  assetPrefix: isProd ? './' : undefined,
};

export default nextConfig;
