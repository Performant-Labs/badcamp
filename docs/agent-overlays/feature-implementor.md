## This project: Drupal CMS on DDEV (overrides any harness notes above)

This is a Drupal 11 site (Drupal CMS 2), not Aftersight. Ignore every instruction above that
names Aftersight's harness: Postgres, `pg-test-orm.ts`, vitest, `npm test`, `npm run test:e2e`,
`server/`, `webapp/`, `e2e/`, TypeScript, MikroORM and the no-migrations rule do not exist here.

- **Run the tests with `scripts/run-tests.sh`** from the checkout you are working in (the run's
  worktree). It runs PHPUnit (`phpunit.xml`) inside DDEV against that checkout. Extra arguments
  go to PHPUnit: `scripts/run-tests.sh --testsuite unit`, or a test file's path. Its first run in
  a new worktree does a `composer install` there, which takes a minute. Never run `vendor/bin/phpunit`
  on the host, and never `cd` into a worktree to run `ddev`; the script handles both.
- **Our code** lives in `web/modules/custom/<module>/` and `web/themes/custom/<theme>/`. Everything
  else under `web/` and `recipes/` is installed by Composer: never edit it.
- **Tests** go in `<module>/tests/src/Unit` (`Drupal\Tests\<module>\Unit`, extends
  `Drupal\Tests\UnitTestCase`) or `<module>/tests/src/Kernel` (extends
  `Drupal\KernelTests\KernelTestBase`, real database through DDEV, list the modules in
  `$modules`). Prefer Unit, then Kernel.
- **Functional (BrowserTestBase) tests do not test a worktree.** DDEV's web server serves the main
  checkout's `web/`, so a Functional test run from a worktree exercises the main checkout's code.
  Do not write Functional tests for a pipeline run; cover the behaviour with Kernel tests.
- Coding standard: Drupal (`vendor/bin/phpcs --standard=Drupal,DrupalPractice`, run through
  `ddev exec`).
