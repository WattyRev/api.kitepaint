# api.kitepaint

This repo contains code for the REST API and connection to KitePaint's Database.

The API is currently in PHP, but I have plans to move it to node.

Tests and deployment are run with GitHub Actions (`.github/workflows/test.yml`). Pushes to `staging` deploy to beta and pushes to `master` deploy to production, after tests pass. Deployment requires the `SFTP_USER` and `SFTP_PASS` repository secrets.
