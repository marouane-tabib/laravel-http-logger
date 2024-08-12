<?php

namespace Chelout\HttpLogger\Processors;

use Monolog\LogRecord;

class RequestDataProcessor
{
    /**
     * Adds additional request data to the log message.
     *
     * @param LogRecord $record
     * @return LogRecord
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        $context = array_merge_recursive(
            $record->context,
            [
                'context' => array_filter([
                    'raw' => $this->processContext('raw'),
                    'data' => $this->processContext('data'),
                    'files' => $this->processContext('files'),
                    'headers' => $this->processContext('headers'),
                    'session' => $this->processContext('session'),
                ]),
                'extra' => $this->processExtra(),
            ]
        );

        return $record->withContext($context);
    }

    /**
     * Process extra.
     *
     * @return array
     */
    protected function processExtra(): array
    {
        return [
            'method' => request()->getMethod(),
            'url' => request()->url(),
            'ips' => implode(', ', request()->ips()),
        ];
    }

    /**
     * Process context.
     *
     * @param string $name data source
     *
     * @return array
     */
    protected function processContext(string $name): array
    {
        $config = config("http-logger.{$name}");

        if (false !== $config) {
            $context = $this->{'get' . ucfirst($name)}();

            if (isset($config['except']) && is_array($config['except'])) {
                $exceptKeys = $config['except'];
                $context = array_diff_key($context, array_flip($exceptKeys));
            }
        
            if (isset($config['only']) && is_array($config['only'])) {
                $onlyKeys = $config['only'];
                $context = array_intersect_key($context, array_flip($onlyKeys));
            }

            return $context;
        }

        return [];
    }

    /**
     * Get request body data except files.
     *
     * @return array
     */
    protected function getRaw(): array
    {
        return [
            request()->getContent(),
        ];
    }

    /**
     * Get request body data except files.
     *
     * @return array
     */
    protected function getData(): array
    {
        return request()->except(
            request()->files->keys()
        );
    }

    /**
     * Get files.
     *
     * @return array
     */
    protected function getFiles(): array
    {
        return collect(request()->files->all())
            ->flatten()
            ->map(function ($file) {
                return [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getClientSize(),
                ];
            })
            ->toArray();
    }

    /**
     * Get request headers.
     *
     * @return array
     */
    protected function getHeaders(): array
    {
        return request()->header();
    }

    /**
     * Get session data.
     *
     * @return array
     */
    protected function getSession(): array
    {
        return session()->all();
    }
}
