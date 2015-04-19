<?php namespace Wms\Admin\DataGrid\Service;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr;
use Wms\Admin\DataGrid\Options\ModuleOptions;
use Wms\Admin\DataGrid\Model\TableModel as Table;

class TableBuilderService
{

    /**
     * @var ModuleOptions
     */
    protected $moduleOptions;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var EntityMetadataHelper
     */
    private $entityMetadataHelper;

    /**
     * @var Array
     */
    protected $availableTableColumns;

    /**
     * @var Array
     */
    protected $selectedTableColumns;

    /**
     * @var QueryBuilder
     */
    private $queryBuilder;

    /**
     * @var Array
     */
    private $subQueries;

    // --------------------------------------------------------------------
    //                         SERVICE INTERACTIONS
    // --------------------------------------------------------------------

    /**
     * @param ModuleOptions $moduleOptions
     * @param EntityManager $entityManager
     * @return TableBuilderService
     */
    public function __construct(ModuleOptions $moduleOptions, EntityManager $entityManager)
    {
        $this->setModuleOptions($moduleOptions);
        $this->setEntityManager($entityManager);
        $this->setQueryBuilder($entityManager->getRepository($moduleOptions->getEntityName())->createQueryBuilder($this->getEntityShortName($moduleOptions->getEntityName())));

        $this->entityMetadataHelper = new EntityMetadataHelper($entityManager);

        // Make sure data retrieval is default when not configured
        $this->setAvailableTableColumns($this->resolveAvailableTableColumns());
        $this->selectColumns($this->getModuleOptions()->getDefaultColumns());
        $this->subQueries = array();
    }

    /**
     * Builds the selectquery for the database, based on the available entity properties
     *
     * @param array $columns
     * @return $this
     * @throws \Exception
     */
    public function selectColumns(array $columns)
    {
        $this->getQueryBuilder()->resetDQLPart('select');
        $this->getQueryBuilder()->resetDQLPart('join');

        $entityMetaData = $this->entityMetadataHelper->getMetaData($this->getModuleOptions()->getEntityName());
        if(!in_array($entityMetaData->getSingleIdentifierFieldName(), $columns)) {
            $columns[] = $entityMetaData->getSingleIdentifierFieldName();
        }

        $joinedProperties = array();
        $entityMetaData = $this->entityMetadataHelper->parseMetaDataToFieldArray($entityMetaData);

        foreach ($columns as $selectColumn) {
            if (!in_array($selectColumn, $this->getAvailableTableColumns())) {
                continue;
            }

            $selectColumnParts = explode(".", $selectColumn);
            $selectColumn = reset($selectColumnParts);
            $columnMetadata = $entityMetaData[$selectColumn];
            $entityShortName = $this->getEntityShortName($this->getModuleOptions()->getEntityName());

            if ($columnMetadata['type'] === 'association') {
                if(empty($columnMetadata['targetEntity'])) {
                    throw new \Exception(sprintf('No target Entity found for %s in Entity %s',
                        $selectColumn['fieldName'], $entityShortName));
                }

                /**
                 * Owning associations can be handled inline
                 * others (OneToMany and ManyToMany) should result in a different query since querying
                 * them will result in multiple duplicate rows in the database resultset
                 */
                if(!$columnMetadata['isOwningSide']) {
                    $this->addSubQuery($selectColumn, $columnMetadata['targetEntity'], end($selectColumnParts));
                    continue;
                }

                if (!isset($columnMetadata['joinColumns']) || empty($columnMetadata['joinColumns'])) {
                    throw new \Exception(sprintf('Can\'t create join query parameters for %s in Entity %s',
                        $columnMetadata['fieldName'], $entityShortName));
                }

                if (!array_key_exists($selectColumn, $joinedProperties)) {
                    $joinedEntityAlias = $this->getEntityShortName($columnMetadata['targetEntity']) . count($joinedProperties);
                    $this->getQueryBuilder()->leftJoin(
                        $entityShortName . '.' . $selectColumn,
                        $joinedEntityAlias
                    );
                    $joinedProperties[$selectColumn] = $joinedEntityAlias;
                } else {
                    $joinedEntityAlias = $joinedProperties[$selectColumn];
                }

                $this->getQueryBuilder()->addSelect(
                    $joinedEntityAlias . '.' . end($selectColumnParts) . ' AS ' . implode($selectColumnParts)
                );
                continue;
            }

            $this->getQueryBuilder()->addSelect($entityShortName . '.' . $selectColumn);
        }

        return $this;
    }

    /**
     * Set the page for pagination
     *
     * @param $pageNumber
     * @param int $itemsPerPage
     * @return $this
     */
    public function setPage($pageNumber, $itemsPerPage = 30)
    {
        $offset = ($pageNumber == 0) ? 0 : ($pageNumber - 1) * $itemsPerPage;
        $this->getQueryBuilder()->setMaxResults($itemsPerPage);
        $this->getQueryBuilder()->setFirstResult($offset);

        return $this;
    }

    /**
     * Retrieve an new TableModel
     * based on your data configuration in the object
     * @return Table
     */
    public function getTable()
    {
        // Retrieve data from Doctrine and the dataprovider
        $primaryData = $this->getQueryBuilder()->getQuery()->execute();
        $primaryKey = $this->entityMetadataHelper->getMetaData($this->getModuleOptions()->getEntityName())->getSingleIdentifierFieldName();

        echo '<pre>';
        var_dump($primaryData);
        echo '</pre>';
        echo 'einde primary data';

        foreach($this->subQueries as $query) {
            $query->setParameter('resultIds', array_column($primaryData, $primaryKey));
            $result = $query->getQuery()->execute();
            echo '<pre>';
            print_r($result);
            echo '</pre>';
            echo 'einde subquery result';
        }

//        die('einde plezier');


        $table = new Table();
        $table->setAvailableHeaders($this->getAvailableTableColumns());
        $table->setAndParseRows($primaryData);

        return $table;
    }

    // --------------------------------------------------------------------
    //                          GETTERS & SETTERS
    // --------------------------------------------------------------------
    /**
     * @return ModuleOptions
     */
    public function getModuleOptions()
    {
        return $this->moduleOptions;
    }

    /**
     * @param ModuleOptions $moduleOptions
     */
    public function setModuleOptions($moduleOptions)
    {
        $this->moduleOptions = $moduleOptions;
    }

    /**
     * @return EntityManager
     */
    public function getEntityManager()
    {
        return $this->entityManager;
    }

    /**
     * @param EntityManager $entityManager
     */
    public function setEntityManager($entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        return $this->queryBuilder;
    }

    /**
     * @param QueryBuilder $queryBuilder
     */
    public function setQueryBuilder($queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * @return string
     */
    public function getEntityShortName($entityName)
    {
        $nameSpaceSegments = explode('\\', $entityName);

        return strtoupper(end($nameSpaceSegments));
    }

    /**
     * @return Array
     */
    public function getAvailableTableColumns()
    {
        return $this->availableTableColumns;
    }

    /**
     * @param Array $availableTableColumns
     */
    public function setAvailableTableColumns($availableTableColumns)
    {
        $this->availableTableColumns = $availableTableColumns;
    }

    // --------------------------------------------------------------------
    //                          INTERNAL LOGIC
    // --------------------------------------------------------------------

    /**
     * Resolve the available columns based on the configured entity.
     * Will also resolve the available columns for the associated properties
     *
     * @return array
     * @throws \Exception
     */
    protected function resolveAvailableTableColumns()
    {
        $entityProperties = $this->entityMetadataHelper->parseMetaDataToFieldArray(
            $this->entityMetadataHelper->getMetaData($this->getModuleOptions()->getEntityName())
        );

        $returnData = array();
        $prohibitedColumns = $this->getModuleOptions()->getProhibitedColumns();

        foreach ($entityProperties as $property) {
            if (in_array($property['fieldName'], $prohibitedColumns)) {
                continue;
            }

            if ($property['type'] != "association") {
                $returnData[] = $property['fieldName'];
                continue;
            }

            if (!isset($property['targetEntity'])) {
                throw new \Exception(
                    sprintf('%s is configured as a association, but no target Entity found', $property['fieldName'])
                );
            }

            $targetEntity = $property['targetEntity'];
            $targetEntityProperties = $this->entityMetadataHelper->parseMetaDataToFieldArray(
                $this->entityMetadataHelper->getMetaData($targetEntity)
            );

            foreach ($targetEntityProperties as $targetEntityProperty) {
                if ($targetEntityProperty['type'] !== "association" && !array_search($targetEntityProperty,
                        $prohibitedColumns)
                ) {
                    $returnData[] = $property['fieldName'] . '.' . $targetEntityProperty['fieldName'];
                }
            }
        }

        return $returnData;
    }

    /**
     * @param $sourceFieldName
     * @param $targetEntityName
     * @param $targetFieldName
     * @throws \Doctrine\ORM\Mapping\MappingException
     * @throws \Exception
     */
    protected function addSubQuery($sourceFieldName, $targetEntityName, $targetFieldName)
    {
        // Get additional information about the association property in the original entity
        $sourceEntity = $this->getModuleOptions()->getEntityName();
        $entityMetadata = $this->entityMetadataHelper->getMetaData($sourceEntity);
        $fieldMetadata = $entityMetadata->getAssociationMapping($sourceFieldName);

        // @todo: ManyToMany: Deze wordt uitgesteld omdat er onenigheid is geconstanteerd in de implementaties hiervan
        if($fieldMetadata['type'] !== ClassMetadataInfo::ONE_TO_MANY) {
            return;
        }

        if(!isset($this->subQueries[$targetEntityName])) {
            $query = $this->getEntityManager()->createQueryBuilder();

            // Validate that we can "auto-join" the entity by letting doctrine do the work
            $associationData = $this->entityMetadataHelper->getMetaData($targetEntityName)->getAssociationsByTargetClass($sourceEntity);
            if(empty($associationData)) {
                throw new \Exception(
                    sprintf("No association data found to bind %s OneToMany with %s", $sourceEntity, $targetEntityName)
                );
            }

            $query->from($targetEntityName, $this->getEntityShortName($targetEntityName));
            $query->join($sourceEntity, $this->getEntityShortName($sourceEntity));
            // @todo ondersteuning voor multi primary key columns
            $query->where($this->getEntityShortName($sourceEntity).'.'.$entityMetadata->getSingleIdentifierFieldName().' IN (:resultIds)');
            $this->subQueries[$targetEntityName] = $query;
        }

        $query = $this->subQueries[$targetEntityName];
        $query->addSelect($this->getEntityShortName($targetEntityName).'.'.$targetFieldName);
    }
}