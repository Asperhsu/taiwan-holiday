<?php

namespace AsperHsu\TaiwanHoliday;

use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Arr;
use GuzzleHttp\Client;
use Exception;

class Crawler
{
    const BASE_URL = 'https://data.taipei/api/v1/dataset';
    const DATASET_ID = '29d9771d-c0ee-40d4-8dfb-3866b0b7adaa';
    const DATE_FORMAT = 'Y/n/j';

    protected $output = 'json';

    public function handle(int $year)
    {
        $rows = (new Collection($this->fetchAllHolidays()))
            ->filter(function ($row) {
                return $this->filterRawRow($row);
            })
            ->map(function ($result) {
                unset($result['_id'], $result['isHoliday']);
                $result['isWeekend'] = $result['holidayCategory'] == '星期六、星期日';
                $result['iso8601'] = Carbon::createFromFormat(static::DATE_FORMAT, $result['date'], 'Asia/Taipei')->startOfDay()->toIso8601String();

                return $result;
            })
            ->filter(function ($row) use ($year) {
                $date = Carbon::parse($row['iso8601']);
                return $date->year == $year;
            })
            ->values()
            ->toArray();

        if (!file_exists($this->output)) {
            mkdir($this->output, 0777, true);
        }

        $filename = $this->output . DIRECTORY_SEPARATOR . $year . '.json';
        file_put_contents($filename, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public function filterRawRow(array $row): bool
    {
        if ($row['isHoliday'] !== '是') {
            return false;
        }

        if (!Carbon::canBeCreatedFromFormat(Arr::get($row, 'date'), static::DATE_FORMAT)) {
            return false;
        }

        return true;
    }

    protected function fetchAllHolidays(): array
    {
        $offset = 0;
        $limit = 1000;
        $records = [];
        $client = new Client([
            'timeout' => 10,
        ]);

        while (true) {
            $data = $this->doFetch($client, $offset, $limit);
            if (!$data) {
                break;
            }

            $fetchCounts = Arr::get($data, 'result.count', 0) - Arr::get($data, 'result.offset', 0);
            $hasMorePage = $fetchCounts > Arr::get($data, 'result.limit', 0);

            $records = array_merge($records, Arr::get($data, 'result.results', []));
            $offset += $limit;

            if (!$hasMorePage) {
                break;
            }
        }

        return $records;
    }

    protected function doFetch(Client $client, int $offset, int $limit): array
    {
        try {
            $res = $client->request('GET', static::BASE_URL . '/' . static::DATASET_ID, [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'query' => [
                    'scope' => 'resourceAquire',
                    'limit' => $limit,
                    'offset' => $offset < 0 ? 0 : $offset,
                ]
            ]);

            $body = $res->getBody();
            return json_decode($body, true);
        } catch (Exception $e) {
            return [];
        }
    }
}
