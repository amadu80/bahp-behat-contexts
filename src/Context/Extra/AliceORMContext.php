<?php

declare(strict_types=1);

namespace Behatch\Context\Extra;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\Tools\SchemaTool;
use Fidry\AliceDataFixtures\LoaderInterface;

class AliceORMContext implements Context
{
    /**
     * @var string
     */
    private $fixturesBasePath;

    /**
     * @var string[]
     */
    private $classes;

    /**
     * @var LoaderInterface
     */
    private $loader;

    /**
     * @var SchemaTool
     */
    private $schemaTool;

    public function __construct(
        Registry        $registry,
        LoaderInterface $loader,
        string          $fixturesBasePath = null
    ) {
        $this->loader = $loader;
        $this->fixturesBasePath = $fixturesBasePath;

        $entityManager = $registry->getManager();
        $this->schemaTool = new SchemaTool($registry->getManager());
        $this->schemaTool->dropDatabase();
        $this->schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $this->schemaTool = new SchemaTool($entityManager);
        $this->classes = $entityManager->getMetadataFactory()->getAllMetadata();
    }

    /**
     * @BeforeScenario @createSchema
     */
    public function createSchema()
    {
        $this->schemaTool->createSchema($this->classes);
    }

    /**
     * @BeforeScenario @dropSchema
     */
    public function dropSchema()
    {
        $this->schemaTool->dropSchema($this->classes);
    }

    /**
     * @Given the database is empty
     * @Then I empty the database
     */
    public function emptyDatabase()
    {
        $this->dropSchema();
        $this->createSchema();
    }

    /**
     * @Given the fixtures :fixturesFile are loaded
     * @Given the fixtures file :fixturesFile is loaded
     * @Given the fixtures :fixturesFile are loaded with the persister :persister
     * @Given the fixtures file :fixturesFile is loaded with the persister :persister
     *
     * @param string             $fixturesFile Path to the fixtures
     */
    public function thereAreFixtures($fixturesFile, $persister = null)
    {
        $this->loadFixtures([$fixturesFile], $persister);
    }

    /**
     * @Given the following fixtures are loaded:
     * @Given the following fixtures files are loaded:
     * @Given the following fixtures are loaded with the persister :persister:
     * @Given the following fixtures files are loaded with the persister :persister:
     *
     * @param TableNode          $fixturesFiles Path to the fixtures
     */
    public function thereAreSeveralFixtures(TableNode $fixturesFileRows, $persister = null)
    {
        $fixturesFiles = [];

        foreach ($fixturesFileRows->getRows() as $fixturesFileRow) {
            $fixturesFiles[] = sprintf('%s%s', $this->fixturesBasePath, $fixturesFileRow[0]);
        }

        $this->loadFixtures($fixturesFiles);
    }

    /**
     * @param array              $fixturesFiles
     * @param PersisterInterface $persister
     */
    private function loadFixtures($fixturesFiles)
    {
        $this->loader->load($fixturesFiles);
    }
}
