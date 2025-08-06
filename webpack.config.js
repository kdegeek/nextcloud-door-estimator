// Webpack config for Nextcloud Door Estimator app frontend
// Uses @nextcloud/webpack-config as base, extended for React+TypeScript

const NextcloudWebpackConfig = require('@nextcloud/webpack-config');
const path = require('path');

const { VueLoaderPlugin } = require('vue-loader');

/** @type {import('webpack').Configuration} */
module.exports = (env, argv) => {
    const isDev = argv.mode === 'development';

    // Extend Nextcloud base config
    const baseConfig = NextcloudWebpackConfig({
        // No default entry, we override below
    });

    // Custom entry points
    baseConfig.entry = {
        'door-estimator': path.resolve(__dirname, 'js', 'door-estimator.js'),
    };

    // Output: single bundle
    baseConfig.output = {
        path: path.resolve(__dirname, 'js'),
        filename: '[name].js', // Use [name] to get 'door-estimator.js'
        publicPath: '/apps/door_estimator/js/', // Nextcloud app static path
        clean: true
    };

    // Module rules: Vue + TypeScript
    baseConfig.module.rules.push(
        {
            test: /\.vue$/,
            loader: 'vue-loader',
        },
        {
            test: /\.ts$/,
            loader: 'ts-loader',
            options: {
                appendTsSuffixTo: [/\.vue$/],
                transpileOnly: true,
            },
            exclude: /node_modules/,
        },
        {
            test: /\.js$/,
            enforce: 'pre',
            use: ['source-map-loader']
        }
    );
    
    baseConfig.plugins.push(new VueLoaderPlugin());

    // Resolve extensions for TS, TSX, JS, JSX, Vue
    baseConfig.resolve = {
        ...baseConfig.resolve,
        extensions: ['.ts', '.js', '.vue', ...(base.resolve?.extensions || [])],
        alias: {
            ...(baseConfig.resolve?.alias || {}),
            'vue$': 'vue/dist/vue.esm-bundler.js',
        }
    };

    // Enforce devtool=false for CSP compliance
    baseConfig.devtool = false;

    // Dev server config (for local development)
    baseConfig.devServer = {
        static: {
            directory: path.join(__dirname, 'js'),
            publicPath: '/js/'
        },
        compress: true,
        port: 9000,
        hot: true,
        devMiddleware: {
            writeToDisk: true
        },
        allowedHosts: 'all',
        headers: {
            'Access-Control-Allow-Origin': '*'
        },
    };

    return baseConfig;
};