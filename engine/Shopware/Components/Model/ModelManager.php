<?php
/**
 * Shopware 4.0
 * Copyright © 2012 shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 *
 * @category   Shopware
 * @package    Shopware_Components_Model
 * @subpackage Model
 * @copyright  Copyright (c) 2012, shopware AG (http://www.shopware.de)
 * @version    $Id$
 * @author     Heiner Lohaus
 * @author     $Author$
 */

namespace Shopware\Components\Model;

use Doctrine\ORM\EntityManager,
    Doctrine\ORM\Tools\DisconnectedClassMetadataFactory,
    Doctrine\ORM\ORMException,
    Doctrine\Common\EventManager,
    Doctrine\DBAL\Connection,
    Doctrine\Common\Util\Inflector,
    Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Global Manager which is responsible for initializing the adapter classes.
 *
 * {@inheritdoc}
 */
class ModelManager extends EntityManager
{
    /**
     * @var \Symfony\Component\Validator\Validator
     */
    protected $validator;

    /**
     * Factory method to create EntityManager instances.
     *
     * @param mixed $conn An array with the connection parameters or an existing
     *      Connection instance.
     * @param Configuration $config The Configuration instance to use.
     * @param \Doctrine\Common\EventManager|null $eventManager The EventManager instance to use.
     * @throws \Doctrine\ORM\ORMException
     * @return ModelManager The created EntityManager.
     */
    public static function create(Connection $conn, Configuration $config, EventManager $eventManager = null)
    {
        if (!$config->getMetadataDriverImpl()) {
            throw ORMException::missingMappingDriverImpl();
        }

        if ($eventManager !== null && $conn->getEventManager() !== $eventManager) {
            throw ORMException::mismatchedEventManager();
        }

        return new self($conn, $config, $conn->getEventManager());
    }

    /**
     * Magic method to build this liquid interface ...
     *
     * @param   string $name
     * @param   array|null $args
     * @return  ModelRepository
     */
    public function __call($name, $args)
    {
        /** @todo make path custom able */
        if(strpos($name, '\\') === false) {
            $name = $name .'\\' . $name;
        }
        $name = 'Shopware\\Models\\' . $name;
        return $this->getRepository($name);
    }

    /**
     * The EntityRepository instances.
     *
     * @var array
     */
    private $repositories = array();

    /**
     * Gets the repository for an entity class.
     *
     * @param string $entityName The name of the entity.
     * @return ModelRepository The repository class.
     */
    public function getRepository($entityName)
    {
        $entityName = ltrim($entityName, '\\');

        if (!isset($this->repositories[$entityName])) {
            $metadata = $this->getClassMetadata($entityName);
            $repositoryClassName = $metadata->customRepositoryClassName;

            if ($repositoryClassName === null) {
                $repositoryClassName = $this->getConfiguration()->getDefaultRepositoryClassName();
            }

            $repositoryClassName = $this->getConfiguration()
                ->getHookManager()->getProxy($repositoryClassName);

            $this->repositories[$entityName] = new $repositoryClassName($this, $metadata);
        }

        return $this->repositories[$entityName];
    }

    /**
     * Serialize an entity to an array
     *
     * @author      Boris Guéry <guery.b@gmail.com>
     * @license     http://sam.zoy.org/wtfpl/COPYING
     * @link        http://borisguery.github.com/bgylibrary
     * @see         https://gist.github.com/1034079#file_serializable_entity.php
     * @param       $entity
     * @return      array
     */
    protected function serializeEntity($entity)
    {
        if ($entity instanceof \Doctrine\ORM\Proxy\Proxy) {
            /** @var $entity \Doctrine\ORM\Proxy\Proxy */
            $entity->__load();
            $className = get_parent_class($entity);
        } else {
            $className = get_class($entity);
        }
        $metadata = $this->getClassMetadata($className);
        $data = array();

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $data[$field] = $metadata->reflFields[$field]->getValue($entity);
        }

        foreach ($metadata->associationMappings as $field => $mapping) {
            $key = Inflector::tableize($field);
            if ($mapping['isCascadeDetach']) {
                $data[$key] = $metadata->reflFields[$field]->getValue($entity);
                if (null !== $data[$key]) {
                    $data[$key] = $this->serializeEntity($data[$key]);
                }
            } elseif ($mapping['isOwningSide'] && $mapping['type'] & ClassMetadata::TO_ONE) {
                if (null !== $metadata->reflFields[$field]->getValue($entity)) {
                    $data[$key] = $this->getUnitOfWork()
                        ->getEntityIdentifier(
                            $metadata->reflFields[$field]
                                ->getValue($entity)
                            );
                } else {
                    // In some case the relationship may not exist, but we want
                    // to know about it
                    $data[$key] = null;
                }
            }
        }

        return $data;
    }

    /**
     * Serialize an entity or an array of entities to an array
     *
     * @param   $entity
     * @return  array
     */
    public function toArray($entity)
    {
        if ($entity instanceof \Traversable) {
           $entity = iterator_to_array($entity);
        }

        if (is_array($entity)) {
            return array_map(array($this, 'serializeEntity'), $entity);
        }

        return $this->serializeEntity($entity);
    }

    /**
     * Returns the total count of the passed query builder.
     *
     * @param \Doctrine\ORM\Query $query
     * @return int|null
     */
    public function getQueryCount(\Doctrine\ORM\Query $query)
    {
        $pagination = new \Doctrine\ORM\Tools\Pagination\Paginator($query);
        return $pagination->count($query);
    }

    /**
     * @return \Doctrine\ORM\QueryBuilder|QueryBuilder
     */
    public function createQueryBuilder()
    {
        return new QueryBuilder($this);
    }

    /**
     * @return \Symfony\Component\Validator\Validator
     */
    public function getValidator()
    {
        if (null === $this->validator) {
            $reader = new \Doctrine\Common\Annotations\AnnotationReader;
            $this->validator = new \Symfony\Component\Validator\Validator(
                new \Symfony\Component\Validator\Mapping\ClassMetadataFactory(
                    new \Symfony\Component\Validator\Mapping\Loader\AnnotationLoader($reader)
                ),
                new \Symfony\Component\Validator\ConstraintValidatorFactory()
            );
        }
        return $this->validator;
    }

    /**
     * @param $object
     * @return \Symfony\Component\Validator\ConstraintViolationList
     */
    public function validate($object)
    {
        return $this->getValidator()->validate($object);
    }

    /**
     * @param array $tableNames
     */
    public function generateAttributeModels($tableNames = null)
    {
        $path = realpath($this->getConfiguration()->getAttributeDir());

        $generator = new Generator();
        $generator->generateAttributeModels($this, $path, $tableNames);
    }

    /**
     * Shopware helper function to extend an attribute table.
     *
     * @param string $table Full table name. Example: "s_user_attributes"
     * @param string $prefix Column prefix. The prefix and column parameter will be the column name. Example: "swag".
     * @param string $column The column name
     * @param string $type Full type declaration. Example: "VARCHAR( 5 )" / "DECIMAL( 10, 2 )"
     * @param bool $nullable Allow null property
     * @param null $default Default value of the column
     * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
     */
    public function addAttribute($table, $prefix, $column, $type, $nullable = true, $default = null)
    {
        // todo@dr throw some more suited exception
        if (empty($table)) {
            throw new \Symfony\Component\Routing\Exception\InvalidParameterException('No table name passed');
        }
        if (strpos($table, '_attributes') === false) {
            throw new \Symfony\Component\Routing\Exception\InvalidParameterException('The passed table name is no attribute table');
        }
        if (empty($prefix)) {
            throw new \Symfony\Component\Routing\Exception\InvalidParameterException('No column prefix passed');
        }
        if (empty($column)) {
            throw new \Symfony\Component\Routing\Exception\InvalidParameterException('No column name passed');
        }
        if (empty($type)) {
            throw new \Symfony\Component\Routing\Exception\InvalidParameterException('No column type passed');
        }

        $null = ($nullable) ? " NULL " : " NOT NULL ";

        if (is_string($default) && strlen($default) > 0) {
            $defaultValue = "'". $default ."'";
        } elseif (is_null($default)) {
            $defaultValue = " NULL ";
        }  else {
            $defaultValue = $default;
        }

        $sql = 'ALTER TABLE ' . $table . ' ADD ' . $prefix . '_' . $column . ' ' . $type . ' ' . $null . ' DEFAULT ' . $defaultValue;
        Shopware()->Db()->query($sql, array($table, $prefix, $column, $type, $null, $defaultValue));
    }

    /**
     * Shopware Helper function to remove an attribute column.
     *
     * @param $table
     * @param $prefix
     * @param $column
     * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
     */
    public function removeAttribute($table, $prefix, $column)
    {
        // todo@dr throw some more suited exception
        if (empty($table)) {
            throw new \Symfony\Component\Routing\Exception\InvalidParameterException('No table name passed');
        }
        if (strpos($table, '_attributes') === false) {
            throw new \Symfony\Component\Routing\Exception\InvalidParameterException('The passed table name is no attribute table');
        }
        if (empty($prefix)) {
            throw new \Symfony\Component\Routing\Exception\InvalidParameterException('No column prefix passed');
        }
        if (empty($column)) {
            throw new \Symfony\Component\Routing\Exception\InvalidParameterException('No column name passed');
        }

        $sql = 'ALTER TABLE ' . $table . ' DROP ' . $prefix . '_' . $column;
        Shopware()->Db()->query($sql);
    }

    /**
     * Generates Doctrine proxy classes
     */
    public function regenerateProxies()
    {
        $metadatas    = $this->getMetadataFactory()->getAllMetadata();
        $proxyFactory = $this->getProxyFactory();
        $proxyFactory->generateProxyClasses($metadatas);
    }
}
