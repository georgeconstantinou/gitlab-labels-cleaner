# GitLab labels cleaner

PHP script for deleting GitLab labels with no issues/merge-requests associations.

## Usage

```bash
php clean-orphan-labels.php [GITLAB_BASE_URL] [GITLAB_ACCESS_TOKEN]
```

## TODO

- Pagination for instances with more than 100 projects
- Pagination for instances with more than 100 labels per-project
