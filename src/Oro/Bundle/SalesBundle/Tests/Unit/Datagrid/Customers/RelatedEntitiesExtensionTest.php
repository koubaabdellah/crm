<?php

namespace Oro\Bundle\SalesBundle\Tests\Unit\Datagrid\Customers;

use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datagrid\ParameterBag;
use Oro\Bundle\DataGridBundle\Datasource\Orm\OrmDatasource;
use Oro\Bundle\DataGridBundle\Exception\DatasourceException;
use Oro\Bundle\EntityBundle\Tests\Unit\ORM\Fixtures\TestEntity;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;
use Oro\Bundle\SalesBundle\Datagrid\Extension\Customers\RelatedEntitiesExtension;
use Oro\Bundle\SalesBundle\Entity\Lead;
use Oro\Bundle\SalesBundle\Entity\Opportunity;
use Oro\Bundle\SalesBundle\EntityConfig\CustomerScope;
use Oro\Bundle\SalesBundle\Provider\Customer\ConfigProvider;

class RelatedEntitiesExtensionTest extends \PHPUnit\Framework\TestCase
{
    /** @var RelatedEntitiesExtension */
    protected $extension;

    /** @var ConfigProvider|\PHPUnit\Framework\MockObject\MockObject */
    protected $configProvider;

    protected function setUp(): void
    {
        $this->configProvider = $this
            ->getMockBuilder(ConfigProvider::class)
            ->disableOriginalConstructor()
            ->setMethods(['isCustomerClass'])
            ->getMock();

        $this->extension = new RelatedEntitiesExtension(
            $this->configProvider,
            Opportunity::class
        );
    }

    /**
     * @dataProvider testIsApplicableDataProvider
     *
     * @param array     $config
     * @param array     $parameters
     * @param bool      $result
     * @param bool|null $enabledConfig
     */
    public function testIsApplicable(array $config, array $parameters, $result, $enabledConfig = null)
    {
        $this->extension->setParameters(new ParameterBag($parameters));
        $this->prepareConfigProvider($parameters, $enabledConfig);
        $this->assertEquals(
            $result,
            $this->extension->isApplicable(DatagridConfiguration::create($config))
        );
    }

    public function testIsApplicableDataProvider()
    {
        $class = TestEntity::class;
        $relatedClass = Opportunity::class;
        return [
            'not orm source type'           => [
                ['source' => ['type' => 'not_orm']],
                [],
                false
            ],
            'no customer_class param'       => [
                ['source' => ['type' => 'orm']],
                ['customer_id' => 1, 'related_entity_class' => $relatedClass],
                false
            ],
            'no customer_id param'          => [
                ['source' => ['type' => 'orm']],
                ['customer_class' => 'test', 'related_entity_class' => $relatedClass],
                false
            ],
            'empty customer_class param'    => [
                ['source' => ['type' => 'orm']],
                ['customer_id' => 1, 'customer_class' => '', 'related_entity_class' => $relatedClass],
                false
            ],
            'empty customer_id param'       => [
                ['source' => ['type' => 'orm']],
                ['customer_class' => $class, 'customer_id' => null, 'related_entity_class' => $relatedClass],
                false
            ],
            'not supported customer class'  => [
                ['source' => ['type' => 'orm']],
                ['customer_class' => $class, 'customer_id' => 1, 'related_entity_class' => $relatedClass],
                false,
                false
            ],
            'invalid related entity class' => [
                ['source' => ['type' => 'orm']],
                ['customer_class' => $class, 'customer_id' => 1, 'related_entity_class' => Lead::class],
                false
            ],
            'all parameters and config set' => [
                ['source' => ['type' => 'orm']],
                ['customer_class' => $class, 'customer_id' => 1, 'related_entity_class' => $relatedClass],
                true,
                true
            ]
        ];
    }

    public function testVisitDatasource()
    {
        $customerClass   = TestEntity::class;
        $customerField   = ExtendHelper::buildAssociationName(
            $customerClass,
            CustomerScope::ASSOCIATION_KIND
        );
        $customerId = 1;
        $customerIdParam = sprintf(':customerIdParam_%s', $customerField);
        $qb = $this->prepareQueryBuilder(Opportunity::class, $customerField, $customerId, $customerIdParam, 'customer');
        $datasource      = $this->getDatasource($qb);
        $config          = DatagridConfiguration::create([]);

        $this->extension->setParameters(
            new ParameterBag(
                [
                    'customer_class' => $customerClass,
                    'customer_id' => $customerId,
                    'related_entity_class' => Opportunity::class
                ]
            )
        );
        $this->extension->visitDatasource($config, $datasource);
    }

    public function testVisitDatasourceNotFoundOpportunityFrom()
    {
        $this->expectException(DatasourceException::class);
        $this->expectExceptionMessage("Couldn't find Oro\Bundle\SalesBundle\Entity\Opportunity alias in QueryBuilder.");

        $qb            = $this->prepareQueryBuilder(TestEntity::class);
        $datasource    = $this->getDatasource($qb);
        $config        = DatagridConfiguration::create([]);
        $this->extension->setParameters(
            new ParameterBag(['customer_class' => TestEntity::class])
        );
        $this->extension->visitDatasource($config, $datasource);
    }

    /**
     * @param bool|null $enabledConfig
     * @param array     $parameters
     */
    protected function prepareConfigProvider(array $parameters, $enabledConfig = null)
    {
        if ($enabledConfig !== null) {
            $this->configProvider
                ->expects($this->once())
                ->method('isCustomerClass')
                ->with($parameters['customer_class'])
                ->willReturn($enabledConfig);
        }
    }

    /**
     * @param string      $opportunityClass
     * @param string      $customerField
     * @param int|null    $customerId
     * @param string|null $customerIdParam
     * @param string|null $alias
     *
     * @return QueryBuilder|\PHPUnit\Framework\MockObject\MockObject
     */
    public function prepareQueryBuilder(
        $opportunityClass,
        $customerField = null,
        $customerId = null,
        $customerIdParam = null,
        $alias = null
    ) {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->never())
            ->method('getDQLPart');
        $from = $this->createMock(Expr\From::class);
        $from->expects($this->once())
            ->method('getFrom')
            ->willReturn($opportunityClass);
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())
            ->method('getDQLPart')
            ->with('from')
            ->willReturn([$from]);
        if (null !== $alias) {
            $expr = $this->createMock(Expr::class);
            $expr->expects($this->any())
                ->method('eq')
                ->with(sprintf('%s.%s', $alias, $customerField), $customerIdParam)
                ->willReturn(sprintf('%s.%s = %s', $alias, $customerField, $customerIdParam));
            $qb->expects($this->any())
                ->method('expr')
                ->willReturn($expr);
            $from->expects($this->once())
                ->method('getAlias')
                ->willReturn($alias);
            $qb->expects($this->once())
                ->method('andWhere')
                ->with(sprintf('%s.%s = %s', $alias, $customerField, $customerIdParam))
                ->will($this->returnSelf());
            $qb->expects($this->once())
                ->method('setParameter')
                ->with($customerIdParam, $customerId);
        }

        return $qb;
    }

    /**
     * @param QueryBuilder|\PHPUnit\Framework\MockObject\MockObject $qb
     *
     * @return OrmDatasource|\PHPUnit\Framework\MockObject\MockObject
     */
    public function getDatasource($qb)
    {
        $datasource = $this->createMock(OrmDatasource::class);
        $datasource->expects($this->once())
            ->method('getQueryBuilder')
            ->willReturn($qb);

        return $datasource;
    }
}
