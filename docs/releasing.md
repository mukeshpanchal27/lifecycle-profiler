# Releasing

## Every release

1. Set the new version in three places:
   - the `Version:` header in `lifecycle-profiler.php`
   - the `LIFECYCLE_PROFILER_VERSION` constant in the same file
   - `Stable tag:` in `readme.txt`
2. Add the changes to `== Changelog ==` in `readme.txt`.
3. If the screens changed, run `npm run assets:wordpress-org` with wp-env running, and commit the new images in `.wordpress-org/`.
4. Merge the release PR to trunk.
5. Tag the merge commit and push the tag:

   ```sh
   git tag -a v1.2.3 -m "Lifecycle Profiler 1.2.3"
   git push origin v1.2.3
   ```

The **Deploy to wordpress.org** workflow runs on the tag:

- It stops if the tag, the header, the constant and the stable tag do not all match.
- It builds the release files from `.distignore` and checks them.
- It commits the release to the wordpress.org SVN repository, in `trunk/` and `tags/1.2.3/`. It also copies `.wordpress-org/` to `assets/`.
- It adds the zip to the GitHub release.

## Before the first deploy

The deploy needs an approved plugin on wordpress.org, and two repository secrets.

1. Run `bin/build-zip.sh`, or download the zip from the **Build zip** workflow of the release commit.
2. Upload the zip at https://wordpress.org/plugins/developers/add/ and wait for the review.
3. After approval, add two secrets in the GitHub repository settings, under **Secrets and variables > Actions**:
   - `SVN_USERNAME`: the wordpress.org username
   - `SVN_PASSWORD`: the SVN password from the wordpress.org account settings
4. Push the version tag. The workflow then makes the first SVN commit.

## Release files

`.distignore` lists the files that stay out of the release. `bin/build-zip.sh` uses the same list. It fails if a required file is missing, or if a development folder such as `vendor/` or `tests/` is in the release.
