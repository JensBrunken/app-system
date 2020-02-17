<?php declare(strict_types=1);

namespace Swag\SaasConnect\Test\Core\Content\App;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Acl\Resource\AclResourceCollection;
use Shopware\Core\Framework\Api\Acl\Resource\AclResourceEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationEntity;
use Swag\SaasConnect\Core\Content\App\Aggregate\ActionButton\ActionButtonEntity;
use Swag\SaasConnect\Core\Content\App\AppCollection;
use Swag\SaasConnect\Core\Content\App\AppEntity;
use Swag\SaasConnect\Core\Content\App\Lifecycle\AppLifecycle;
use Swag\SaasConnect\Core\Content\App\Manifest\Manifest;
use Swag\SaasConnect\Core\Framework\Webhook\WebhookEntity;

class AppLifecycleTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var AppLifecycle
     */
    private $appLifecycle;

    /**
     * @var EntityRepositoryInterface
     */
    private $appRepository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var EntityRepositoryInterface
     */
    private $actionButtonRepository;

    public function setUp(): void
    {
        $this->appRepository = $this->getContainer()->get('app.repository');
        $this->actionButtonRepository = $this->getContainer()->get('app_action_button.repository');

        $this->appLifecycle = $this->getContainer()->get(AppLifecycle::class);
        $this->context = Context::createDefaultContext();
    }

    public function testInstall(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/Manifest/_fixtures/test/manifest.xml');
        $this->appLifecycle->install($manifest, $this->context);

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        static::assertEquals('SwagApp', $apps->first()->getName());
        static::assertEquals(
            base64_encode(file_get_contents(__DIR__ . '/Manifest/_fixtures/test/icon.png')),
            $apps->first()->getIcon()
        );

        $this->assertDefaultActionButtons();
        $this->assertDefaultModules($apps->first());
        $this->assertDefaultPrivileges($apps->first()->getAclRoleId());
        $this->assertDefaultCustomFields($apps->first()->getId());
        $this->assertDefaultWebhooks($apps->first()->getId());
    }

    public function testInstallMinimalManifest(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/Manifest/_fixtures/minimal/manifest.xml');
        $this->appLifecycle->install($manifest, $this->context);

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        static::assertEquals('SwagAppMinimal', $apps->first()->getName());
    }

    public function testUpdate(): void
    {
        $id = Uuid::randomHex();
        $roleId = Uuid::randomHex();

        $this->appRepository->create([[
            'id' => $id,
            'name' => 'SwagApp',
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'modules' => [
                [
                    'label' => [
                        'en-GB' => 'will be overwritten',
                    ],
                    'source' => 'https://example.com',
                ],
            ],
            'actionButtons' => [
                [
                    'action' => 'test',
                    'entity' => 'order',
                    'view' => 'detail',
                    'label' => 'test',
                    'url' => 'test.com',
                ],
                [
                    'action' => 'viewOrder',
                    'entity' => 'should',
                    'view' => 'get',
                    'label' => 'updated',
                    'url' => 'test.com',
                ],
            ],
            'integration' => [
                'label' => 'test',
                'writeAccess' => false,
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'customFieldSets' => [
                [
                    'name' => 'test',
                ],
            ],
            'aclRole' => [
                'id' => $roleId,
                'name' => 'SwagApp',
            ],
            'webhooks' => [
                [
                    'name' => 'hook1',
                    'url' => 'oldUrl.com',
                    'eventName' => 'testEvent',
                ],
                [
                    'name' => 'shouldGetDeleted',
                    'url' => 'test.com',
                    'eventName' => 'anotherTest',
                ],
            ],
        ]], $this->context);

        /** @var Connection $connection */
        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeUpdate('
            INSERT INTO `acl_resource` (`resource`, `privilege`, `acl_role_id`, `created_at`)
            VALUES ("test", "list", UNHEX(:roleId), NOW()), ("product", "detail", UNHEX(:roleId), NOW())
        ', ['roleId' => $roleId]);

        $app = [
            'id' => $id,
            'roleId' => $roleId,
        ];

        $manifest = Manifest::createFromXmlFile(__DIR__ . '/Manifest/_fixtures/test/manifest.xml');
        $this->appLifecycle->update($manifest, $app, $this->context);

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();

        static::assertCount(1, $apps);
        static::assertEquals('SwagApp', $apps->first()->getName());
        static::assertEquals(
            base64_encode(file_get_contents(__DIR__ . '/Manifest/_fixtures/test/icon.png')),
            $apps->first()->getIcon()
        );
        static::assertEquals('1.0.0', $apps->first()->getVersion());
        static::assertNotEquals('test', $apps->first()->getTranslation('label'));

        $this->assertDefaultActionButtons();
        $this->assertDefaultModules($apps->first());
        $this->assertDefaultPrivileges($apps->first()->getAclRoleId());
        $this->assertDefaultCustomFields($id);
        $this->assertDefaultWebhooks($apps->first()->getId());
    }

    public function testDelete(): void
    {
        $appId = Uuid::randomHex();
        $this->appRepository->create([[
            'id' => $appId,
            'name' => 'Test',
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'actionButtons' => [
                [
                    'entity' => 'order',
                    'view' => 'detail',
                    'action' => 'test',
                    'label' => 'test',
                    'url' => 'test.com',
                ],
            ],
            'integration' => [
                'label' => 'test',
                'writeAccess' => false,
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'SwagApp',
            ],
        ]], $this->context);

        $app = [
            'id' => $appId,
        ];

        $this->appLifecycle->delete('Test', $app, $this->context);

        $apps = $this->appRepository->searchIds(new Criteria([$appId]), $this->context)->getIds();
        static::assertCount(0, $apps);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $apps = $this->actionButtonRepository->searchIds($criteria, $this->context)->getIds();
        static::assertCount(0, $apps);
    }

    private function assertDefaultActionButtons(): void
    {
        $actionButtons = $this->actionButtonRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(2, $actionButtons);
        $actionNames = array_map(function (ActionButtonEntity $actionButton) {
            return $actionButton->getAction();
        }, $actionButtons->getElements());

        static::assertContains('viewOrder', $actionNames);
        static::assertContains('doStuffWithProducts', $actionNames);
    }

    private function assertDefaultModules(AppEntity $app): void
    {
        static::assertCount(2, $app->getModules());

        static::assertEquals([
            'label' => [
                'en-GB' => 'My first own module',
                'de-DE' => 'Mein erstes eigenes Modul',
            ],
            'source' => 'https://test.com',
            'name' => 'first-module',
        ], $app->getModules()[0]);

        static::assertEquals([
            'label' => [
                'en-GB' => 'My second module',
            ],
            'source' => 'https://test.com/second',
            'name' => 'second-module',
        ], $app->getModules()[1]);
    }

    private function assertDefaultPrivileges(string $roleId): void
    {
        /** @var EntityRepositoryInterface $aclResourceRepository */
        $aclResourceRepository = $this->getContainer()->get('acl_resource.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('aclRoleId', $roleId));
        /** @var AclResourceCollection $privileges */
        $privileges = $aclResourceRepository->search($criteria, $this->context)->getEntities();

        static::assertCount(14, $privileges);
        $this->assertPrivilegesContains('list', 'product', $privileges);
        $this->assertPrivilegesContains('detail', 'product', $privileges);
        $this->assertPrivilegesContains('create', 'product', $privileges);
        $this->assertPrivilegesContains('update', 'product', $privileges);
        $this->assertPrivilegesContains('delete', 'product', $privileges);

        $this->assertPrivilegesContains('list', 'category', $privileges);
        $this->assertPrivilegesContains('delete', 'category', $privileges);

        $this->assertPrivilegesContains('list', 'product_manufacturer', $privileges);
        $this->assertPrivilegesContains('delete', 'product_manufacturer', $privileges);
        $this->assertPrivilegesContains('create', 'product_manufacturer', $privileges);
        $this->assertPrivilegesContains('detail', 'product_manufacturer', $privileges);

        $this->assertPrivilegesContains('list', 'tax', $privileges);
        $this->assertPrivilegesContains('create', 'tax', $privileges);
        $this->assertPrivilegesContains('detail', 'tax', $privileges);
    }

    private function assertPrivilegesContains(string $privilege, string $resource, AclResourceCollection $privileges): void
    {
        static::assertCount(
            1,
            $privileges->filter(function (AclResourceEntity $aclResource) use ($privilege, $resource): bool {
                return $aclResource->getPrivilege() === $privilege && $aclResource->getResource() === $resource;
            }),
            sprintf('AclResourceCollection does not contain Privilege for "%s" for entity "%s"', $privilege, $resource)
        );
    }

    private function assertDefaultCustomFields(string $appId): void
    {
        /** @var EntityRepositoryInterface $customFieldSetRepository */
        $customFieldSetRepository = $this->getContainer()->get('custom_field_set.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addAssociation('relations');

        /** @var CustomFieldSetCollection $customFieldSets */
        $customFieldSets = $customFieldSetRepository->search($criteria, $this->context)->getEntities();

        static::assertCount(1, $customFieldSets);

        $customFieldSet = $customFieldSets->first();
        static::assertEquals('custom_field_test', $customFieldSet->getName());
        static::assertCount(2, $customFieldSet->getRelations());

        $relatedEntities = array_map(function (CustomFieldSetRelationEntity $relation) {
            return $relation->getEntityName();
        }, $customFieldSet->getRelations()->getElements());
        static::assertContains('product', $relatedEntities);
        static::assertContains('customer', $relatedEntities);

        static::assertEquals([
            'label' => [
                'en-GB' => 'Custom field test',
                'de-DE' => 'Zusatzfeld Test',
            ],
            'translated' => true,
        ], $customFieldSet->getConfig());
    }

    private function assertDefaultWebhooks(string $appId): void
    {
        /** @var EntityRepositoryInterface $webhookRepository */
        $webhookRepository = $this->getContainer()->get('webhook.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        $webhooks = $webhookRepository->search($criteria, $this->context)->getElements();

        static::assertCount(2, $webhooks);

        usort($webhooks, static function (WebhookEntity $a, WebhookEntity $b): int {
            return $a->getUrl() <=> $b->getUrl();
        });

        /** @var WebhookEntity $firstWebhook */
        $firstWebhook = $webhooks[0];
        static::assertEquals('https://test.com/hook', $firstWebhook->getUrl());
        static::assertEquals('checkout.customer.before.login', $firstWebhook->getEventName());

        /** @var WebhookEntity $secondWebhook */
        $secondWebhook = $webhooks[1];
        static::assertEquals('https://test.com/hook2', $secondWebhook->getUrl());
        static::assertEquals('checkout.order.placed', $secondWebhook->getEventName());
    }
}
