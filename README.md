# Lifecycle Profiler

Lifecycle Profiler is a WordPress plugin that times the whole request. It measures bootstrap, the load time of each plugin and every hook callback. It sends the results in the `Server-Timing` header and offers a timing API.

## Status

Early development. The plugin activates but does not profile anything yet. See the [milestones](https://github.com/mukeshpanchal27/lifecycle-profiler/milestones) for the roadmap.

## Requirements

- PHP 7.4 or later
- WordPress 6.8 or later

## Local development

You need Node.js and Docker.

```sh
npm install
npx wp-env start
```

The site runs at http://localhost:8888 with the plugin active. Log in with `admin` and `password`.

## License

[GPL-3.0-or-later](LICENSE)
