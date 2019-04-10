<?php
/*
 * This file is part of the App Search Magento module.
 *
 * (c) Elastic
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Elastic\AppSearch\Model\Adapter\Engine\Schema;

use Elastic\AppSearch\Model\Adapter\Engine\SchemaInterface;

/**
 * AppSearch Engine Schema builder description.
 *
 * @package   Elastic\Model\Adapter\Engine
 * @copyright 2019 Elastic
 * @license   Open Software License ("OSL") v. 3.0
 */
interface BuilderInterface
{
    /**
     * Add a new field to the schema.
     *
     * @return BuilderInterface
     */
    public function addField(string $fieldName, string $fieldType);

    /**
     * Add a several fields to the schema.
     *
     * @return BuilderInterface
     */
    public function addFields(array $fields);

    /**
     * Build the schema and return it.
     *
     * @return SchemaInterface
     */
    public function build(): SchemaInterface;
}
