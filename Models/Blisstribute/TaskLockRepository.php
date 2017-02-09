<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
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
 */

namespace Shopware\CustomModels\Blisstribute;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\NonUniqueResultException;
use Shopware\Components\Model\ModelRepository;

/**
 * db repository class for task lock
 *
 * @author    Julian Engler
 * @package   Shopware\CustomModels\Blisstribute
 * @copyright Copyright (c) 2016
 * @since     1.0.0
 */
class TaskLockRepository extends ModelRepository
{
    /**
     * get task lock by task name and process id
     *
     * @param string $taskName
     *
     * @return TaskLock
     *
     * @throws NonUniqueResultException
     */
    public function findByTaskName($taskName)
    {
        $query = $this->getTaskLockBaseDataQuery($taskName);
        return $query->getOneOrNullResult();
    }

    /**
     * Used for the task backend module to load the blisstribute task data into
     * the module.
     *
     * @param string $taskName
     *
     * @return Query
     */
    public function getTaskLockBaseDataQuery($taskName)
    {
        return $this->getTaskLockBaseDataQueryBuilder($taskName)->getQuery();
    }

    /**
     * Used for the task backend module to load the task data into
     * the module.
     *
     * @param string $taskName
     *
     * @return QueryBuilder
     */
    public function getTaskLockBaseDataQueryBuilder($taskName)
    {
        $builder = $this->getEntityManager()->createQueryBuilder();
        $builder->select('t');
        $builder->from('Shopware\CustomModels\Blisstribute\TaskLock', 't')
            ->where('t.taskName = :taskName')
            ->setParameter('taskName', $taskName);

        return $builder;
    }
}
