<?php

namespace AsyncAws\Athena\Result;

use AsyncAws\Athena\AthenaClient;
use AsyncAws\Athena\Input\GetQueryResultsInput;
use AsyncAws\Athena\ValueObject\ColumnInfo;
use AsyncAws\Athena\ValueObject\Datum;
use AsyncAws\Athena\ValueObject\ResultSet;
use AsyncAws\Athena\ValueObject\ResultSetMetadata;
use AsyncAws\Athena\ValueObject\Row;
use AsyncAws\Core\Exception\InvalidArgument;
use AsyncAws\Core\Response;
use AsyncAws\Core\Result;

/**
 * @implements \IteratorAggregate<Row>
 */
class GetQueryResultsOutput extends Result implements \IteratorAggregate
{
    /**
     * The number of rows inserted with a `CREATE TABLE AS SELECT` statement.
     */
    private $updateCount;

    /**
     * The results of the query execution.
     */
    private $resultSet;

    /**
     * A token generated by the Athena service that specifies where to continue pagination if a previous request was
     * truncated. To obtain the next set of pages, pass in the `NextToken` from the response object of the previous page
     * call.
     */
    private $nextToken;

    /**
     * Iterates over ResultSet.Rows.
     *
     * @return \Traversable<Row>
     */
    public function getIterator(): \Traversable
    {
        $client = $this->awsClient;
        if (!$client instanceof AthenaClient) {
            throw new InvalidArgument('missing client injected in paginated result');
        }
        if (!$this->input instanceof GetQueryResultsInput) {
            throw new InvalidArgument('missing last request injected in paginated result');
        }
        $input = clone $this->input;
        $page = $this;
        while (true) {
            $page->initialize();
            if ($page->nextToken) {
                $input->setNextToken($page->nextToken);

                $this->registerPrefetch($nextPage = $client->getQueryResults($input));
            } else {
                $nextPage = null;
            }

            yield from $page->getResultSet()->getRows();

            if (null === $nextPage) {
                break;
            }

            $this->unregisterPrefetch($nextPage);
            $page = $nextPage;
        }
    }

    public function getNextToken(): ?string
    {
        $this->initialize();

        return $this->nextToken;
    }

    public function getResultSet(): ?ResultSet
    {
        $this->initialize();

        return $this->resultSet;
    }

    public function getUpdateCount(): ?int
    {
        $this->initialize();

        return $this->updateCount;
    }

    protected function populateResult(Response $response): void
    {
        $data = $response->toArray();

        $this->updateCount = isset($data['UpdateCount']) ? (int) $data['UpdateCount'] : null;
        $this->resultSet = empty($data['ResultSet']) ? null : $this->populateResultResultSet($data['ResultSet']);
        $this->nextToken = isset($data['NextToken']) ? (string) $data['NextToken'] : null;
    }

    private function populateResultColumnInfo(array $json): ColumnInfo
    {
        return new ColumnInfo([
            'CatalogName' => isset($json['CatalogName']) ? (string) $json['CatalogName'] : null,
            'SchemaName' => isset($json['SchemaName']) ? (string) $json['SchemaName'] : null,
            'TableName' => isset($json['TableName']) ? (string) $json['TableName'] : null,
            'Name' => (string) $json['Name'],
            'Label' => isset($json['Label']) ? (string) $json['Label'] : null,
            'Type' => (string) $json['Type'],
            'Precision' => isset($json['Precision']) ? (int) $json['Precision'] : null,
            'Scale' => isset($json['Scale']) ? (int) $json['Scale'] : null,
            'Nullable' => isset($json['Nullable']) ? (string) $json['Nullable'] : null,
            'CaseSensitive' => isset($json['CaseSensitive']) ? filter_var($json['CaseSensitive'], \FILTER_VALIDATE_BOOLEAN) : null,
        ]);
    }

    /**
     * @return ColumnInfo[]
     */
    private function populateResultColumnInfoList(array $json): array
    {
        $items = [];
        foreach ($json as $item) {
            $items[] = $this->populateResultColumnInfo($item);
        }

        return $items;
    }

    private function populateResultDatum(array $json): Datum
    {
        return new Datum([
            'VarCharValue' => isset($json['VarCharValue']) ? (string) $json['VarCharValue'] : null,
        ]);
    }

    /**
     * @return Datum[]
     */
    private function populateResultDatumList(array $json): array
    {
        $items = [];
        foreach ($json as $item) {
            $items[] = $this->populateResultDatum($item);
        }

        return $items;
    }

    private function populateResultResultSet(array $json): ResultSet
    {
        return new ResultSet([
            'Rows' => !isset($json['Rows']) ? null : $this->populateResultRowList($json['Rows']),
            'ResultSetMetadata' => empty($json['ResultSetMetadata']) ? null : $this->populateResultResultSetMetadata($json['ResultSetMetadata']),
        ]);
    }

    private function populateResultResultSetMetadata(array $json): ResultSetMetadata
    {
        return new ResultSetMetadata([
            'ColumnInfo' => !isset($json['ColumnInfo']) ? null : $this->populateResultColumnInfoList($json['ColumnInfo']),
        ]);
    }

    private function populateResultRow(array $json): Row
    {
        return new Row([
            'Data' => !isset($json['Data']) ? null : $this->populateResultDatumList($json['Data']),
        ]);
    }

    /**
     * @return Row[]
     */
    private function populateResultRowList(array $json): array
    {
        $items = [];
        foreach ($json as $item) {
            $items[] = $this->populateResultRow($item);
        }

        return $items;
    }
}
