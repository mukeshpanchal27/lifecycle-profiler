# Security policy

## Supported versions

Only the latest release gets security fixes. Update to the newest version before you report a problem, and check whether it still happens.

| Version | Security fixes |
| --- | --- |
| Latest 1.x release | Yes |
| 0.x | No |

## Report a vulnerability

Do not open a public issue for a security problem. A public issue shows the problem to everyone before a fix exists.

Report it privately on GitHub instead:

1. Open the [Security tab](https://github.com/mukeshpanchal27/lifecycle-profiler/security) of this repository.
2. Click **Report a vulnerability**.
3. Describe the problem, the affected version, and the steps to reproduce it.

Only the maintainer can see the report. The maintainer replies on the report, works on a fix, and publishes a security advisory with the fixed release. You get credit in the advisory, unless you ask not to be named.

## What counts

Lifecycle Profiler is a developer tool for administrators. These are security problems:

- A user without the `manage_options` capability can start a profiling session, see a report or change one.
- A visitor gets the `Server-Timing` header or a saved report.
- A form or action works without a valid nonce.
- Output that is not escaped, or input that is not sanitized.
- The mu-plugin loader file can be used to run code that the plugin did not write.

Reports and the `Server-Timing` header show timing data, file paths and hook names to the administrator who profiles. That is the purpose of the plugin, so it is not a security problem on its own.

The [security review](docs/security-review.md) lists how each action, input and output is protected.
