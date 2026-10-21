<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Production only ships after a human approves it, and that gate lives in two
 * places: `environment: production` in the workflow file, and the required
 * reviewers on that GitHub Environment. The reviewers are an account setting
 * this repository cannot check - but the YAML line is one careless edit away
 * from being deleted, and deleting it publishes the live folder the moment a
 * push lands. The dev workflow next to it has no gate at all, so copying that
 * file and renaming the target is exactly how the line goes missing.
 *
 * See docs/CPANEL-DEPLOYMENT.md, sections 3e and 3f.
 */
class ProductionDeployGateTest extends TestCase
{
    /**
     * Every workflow that can write to the live host has to declare the
     * `production` environment: that declaration is what makes the run sit in
     * "Waiting" until a required reviewer approves it.
     */
    public function test_every_workflow_that_ships_the_live_host_is_gated()
    {
        foreach (['deploy-cpanel-prod.yml', 'retire-legacy-host.yml'] as $file) {
            $yaml = $this->workflowWithoutComments($file);

            $this->assertSame(
                1,
                preg_match('/^\s*environment:\s*production\s*(#.*)?$/m', $yaml),
                $file . ' no longer declares `environment: production`. Without that line the run is '
                . "ungated - a push to main would publish the live site immediately, with no "
                . "\"Approve and deploy\" step and no Pending state."
            );
        }
    }

    /**
     * `master` still exists on the remote. If production accepted it, a push to
     * that stale branch would mirror an older tree over the live folder and
     * silently un-ship whatever `main` had already deployed.
     */
    public function test_the_production_workflow_ships_main_only()
    {
        $yaml = $this->workflowWithoutComments('deploy-cpanel-prod.yml');

        $this->assertStringNotContainsString(
            'master',
            $yaml,
            'deploy-cpanel-prod.yml must accept `main` only: any other branch can still be shipped '
            . 'deliberately with the Actions > "Run workflow" button.'
        );

        $this->assertMatchesRegularExpression(
            '/^\s*-\s*main\s*(#.*)?$/m',
            $yaml,
            'deploy-cpanel-prod.yml no longer lists `main` under `on: push: branches:`.'
        );
    }

    /**
     * The dev deploy has no gate, so it must never be able to reach the live
     * host: no `production` environment (which would put every dev push behind
     * the approval it is meant to bypass) and no `PROD_` credentials.
     */
    public function test_the_dev_workflow_never_touches_the_production_environment()
    {
        $yaml = $this->workflowWithoutComments('deploy-cpanel.yml');

        $this->assertStringNotContainsString(
            'PROD_CPANEL',
            $yaml,
            'deploy-cpanel.yml reads a PROD_CPANEL_* value. The dev workflow publishes the dev '
            . 'folder on every push without review; it must never hold credentials for the live host.'
        );

        $this->assertSame(
            0,
            preg_match('/^\s*environment:\s*production\s*(#.*)?$/m', $yaml),
            'deploy-cpanel.yml declares `environment: production`, so every dev push would wait for '
            . 'the production approval gate - and a dev push could ship to the live host.'
        );
    }

    /**
     * A collaborator with the Write role can edit the workflow files, including
     * the `environment: production` line above. `.github/CODEOWNERS` is what
     * makes that edit need the owner's review, but only if a rule for `main`
     * asks for it ("Require review from Code Owners" in
     * Settings > Rules > `main`) - see section 3f.
     */
    public function test_codeowners_covers_the_workflow_directory()
    {
        $path = base_path('.github/CODEOWNERS');

        $this->assertFileExists(
            $path,
            'The deploy gate can be edited away by anyone with Write access. Add .github/CODEOWNERS '
            . 'naming the repository owner for /.github/ so a workflow change needs their review.'
        );

        $covered = false;

        foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
            $line = trim($line);

            if ($line === '' || substr($line, 0, 1) === '#') {
                continue;
            }

            $parts = preg_split('/\s+/', $line, 2);
            $pattern = $parts[0];
            $owners = $parts[1] ?? '';

            if (in_array($pattern, ['/.github/', '.github/'], true) && strpos($owners, '@') !== false) {
                $covered = true;
            }
        }

        $this->assertTrue(
            $covered,
            '.github/CODEOWNERS has no rule owning `/.github/` with an owner, so workflow edits are '
            . 'not routed to a reviewer.'
        );
    }

    /**
     * The YAML with its comment lines removed: the files carry long explanatory
     * comments that mention the very strings asserted above ("Do NOT allow
     * master", "environment: production" in prose), so a raw search would pass
     * a file with the real line deleted.
     */
    private function workflowWithoutComments(string $file): string
    {
        $path = base_path('.github/workflows/' . $file);

        $this->assertFileExists($path);

        $lines = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
            if (substr(ltrim($line), 0, 1) === '#') {
                continue;
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }
}
