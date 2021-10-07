<?php

declare(strict_types=1);

final class OrphanLabelsCleaner
{
    private string $baseUrl;
    private string $authToken;

    private function __construct(string $baseUrl, string $authToken)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->authToken = $authToken;
    }

    /** @param array<int, string> $arguments */
    public static function fromCli(array $arguments): self
    {
        $errors = [];

        if (! array_key_exists(1, $arguments)) {
            $errors[] = 'Please provide Gitlab instance base URL.';
        }

        if (! array_key_exists(2, $arguments)) {
            $errors[] = 'Please provide Gitlab private token.';
        }

        if ([] !== $errors) {
            self::exitWithError($errors);
        }

        return new self($arguments[1], $arguments[2]);
    }

    public function clean(): void
    {
        foreach ($this->getProjects() as $project) {
            $projectId = $project['id'] ?? -1;

            echo 'Processing project "' . $project['name'] . ' [' . $projectId . ']" ..' . PHP_EOL;

            $labels = $this->getProjectLabels($projectId);
            $orphanLabels = $this->filterLabels($labels);

            $this->deleteLabels($projectId, $orphanLabels);

            echo PHP_EOL;
        }

        echo PHP_EOL;
    }

    /** @return array<int, array<string, mixed>> */
    private function getProjects(): array
    {
        $resource = curl_init();

        curl_setopt_array($resource, [
            CURLOPT_URL => sprintf('%s/api/v4/projects?simple=true&per_page=100', $this->baseUrl),
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => [sprintf('PRIVATE-TOKEN: %s', $this->authToken)]
        ]);

        $response = curl_exec($resource);

        curl_close($resource);

        $response = (array)json_decode((string)$response, true);

        if (array_key_exists('error', $response)) {
            self::exitWithError([$response['error_description'] ?? '']);
        }

        return $response;
    }

    /** @return array<int, array<string, mixed>> */
    private function getProjectLabels(int $projectId): array
    {
        $resource = curl_init();

        curl_setopt_array($resource, [
            CURLOPT_URL => sprintf(
                '%s/api/v4/projects/%d/labels?with_counts=true&per_page=100',
                $this->baseUrl,
                $projectId
            ),
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => [sprintf('PRIVATE-TOKEN: %s', $this->authToken)]
        ]);

        $response = curl_exec($resource);

        curl_close($resource);

        $response = (array)json_decode((string)$response, true);

        if (array_key_exists('error', $response)) {
            self::exitWithError([$response['error_description'] ?? '']);
        }

        return $response;
    }

    /**
     * @param array<int, array<string, mixed>> $labels
     * @return array<int, array<string, mixed>>
     */
    private function filterLabels(array $labels): array
    {
        return array_filter($labels, function ($label) {
            if (! $label['is_project_label']) {
                return false;
            }

            if (0 < $label['open_issues_count']) {
                echo '[SKIP] ' . $label['name'] . ' has open issues' . PHP_EOL;
                return false;
            }

            if (0 < $label['closed_issues_count']) {
                echo '[SKIP] ' . $label['name'] . ' has closed issues' . PHP_EOL;
                return false;
            }

            if (0 < $label['open_merge_requests_count']) {
                echo '[SKIP] ' . $label['name'] . ' has open merge requests' . PHP_EOL;
                return false;
            }

            return true;
        });
    }

    /** @param array<int, array<string, mixed>> $labels */
    private function deleteLabels(int $projectId, array $labels): void
    {
        foreach ($labels as $label) {
            $labelId = $label['id'] ?? -1;

            echo 'deleting label "' . $label['name'] . ' [' . $labelId . ']" ..';
            $this->deleteLabel($projectId, $labelId);
            echo ' done' . PHP_EOL;
        }
    }

    private function deleteLabel(int $projectId, int $labelId): void
    {
        $resource = curl_init();

        curl_setopt_array($resource, [
            CURLOPT_URL => sprintf(
                '%s/api/v4/projects/%d/labels/%d',
                $this->baseUrl,
                $projectId,
                $labelId
            ),
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => [sprintf('PRIVATE-TOKEN: %s', $this->authToken)]
        ]);

        $response = curl_exec($resource);

        curl_close($resource);

        $response = (array)json_decode((string)$response, true);

        if (array_key_exists('error', $response)) {
            self::exitWithError([$response['error_description'] ?? '']);
        }
    }

    /** @param array<int, string> $errors */
    private static function exitWithError(array $errors): void
    {
        foreach ($errors as $error) {
            $error = '' === trim($error) ? 'Unknown.' : $error;

            echo sprintf('[ERROR] %s ', $error) . PHP_EOL;
        }

        echo PHP_EOL;
        exit(1);
    }
}

OrphanLabelsCleaner::fromCli($argv)->clean();
