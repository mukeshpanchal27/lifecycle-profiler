# Contributing

Thank you for helping with Lifecycle Profiler. This guide explains how to set up the plugin, make a change and open a pull request.

By taking part, you agree to follow the [code of conduct](CODE_OF_CONDUCT.md). To report a security problem, follow the [security policy](SECURITY.md) instead of opening an issue.

## Issues

Use the issue forms. **Bug report** asks for the steps to reproduce the problem, the WordPress version and the PHP version. **Enhancement** asks what you want to do and why.

Search the open issues first, in case someone has already reported the same thing.

## Set up

You need Node.js, Composer and Docker.

```sh
npm ci
composer install
npm exec --no -- wp-env start
```

The development site runs at http://localhost:8888. Log in with `admin` and `password`.

Run tools with `npm exec --no --`, not `npx`. `npx` downloads a package that is not installed and runs its install scripts.

## Make a change

1. Create a branch from `trunk`. Name it after the type and the issue, for example `fix/71-reinstall-loader` or `feature/26-template-time`.
2. Make the change. Follow the style of the code around it.
3. Add or update tests in `tests/phpunit/` or `tests/e2e/`.
4. Run the checks:

   ```sh
   composer lint
   npm run test:php
   npm run test:php:multisite
   npm run test:e2e
   ```

5. If the change is visible to users, add a line to `== Changelog ==` in `readme.txt`.

## Commit messages

Write commit messages in lowercase, with a type, a colon and a short summary:

```
fix: write the loader when it is missing after an update
```

The types in use are `feature`, `enhancement`, `fix`, `security`, `performance`, `testing`, `documentation`, `infrastructure` and `chore`. Keep the first line under 72 characters. In the body, explain why the change is needed.

## Pull requests

Fill in the pull request template. Say which issue the pull request fixes, what changed and how you tested it.

All required checks must pass before a pull request can merge:

| Check | What it checks |
| --- | --- |
| PHP 7.4 to PHP 8.5 | PHP syntax |
| PHPCS | WordPress coding standards and PHP 7.4 compatibility |
| PHPUnit and PHPUnit multisite | The PHPUnit tests |
| E2E | The Playwright tests |
| Plugin Check | The release files, with the official wordpress.org tool |
| Spell check | Misspellings. Add allowed words to `.github/typos.toml`. |
| Build zip | The release zip and its files |

## GitHub Actions

Pin every action to a full commit hash, with the exact tag name in a comment:

```yaml
- uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
```

Use the tag name exactly as the action's repository writes it, with or without a `v`. Choose a release that is at least two weeks old.

## Releases

Only the maintainer makes releases. The steps are in [docs/releasing.md](docs/releasing.md).
