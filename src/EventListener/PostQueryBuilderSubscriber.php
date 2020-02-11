<?php

namespace AlterPHP\EasyAdminExtensionBundle\EventListener;

use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Event\EasyAdminEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Apply filters on list/search queryBuilder.
 */
class PostQueryBuilderSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            EasyAdminEvents::POST_LIST_QUERY_BUILDER => array('onPostListQueryBuilder'),
            EasyAdminEvents::POST_SEARCH_QUERY_BUILDER => array('onPostSearchQueryBuilder'),
        );
    }

    /**
     * Called on POST_LIST_QUERY_BUILDER event.
     *
     * @param GenericEvent $event
     */
    public function onPostListQueryBuilder(GenericEvent $event)
    {
        $queryBuilder = $event->getArgument('query_builder');

        if ($event->hasArgument('request')) {
            $this->applyRequestFilters($queryBuilder, $event->getArgument('request')->get('filters', array()));
            $this->applyFormFilters($queryBuilder, $event->getArgument('request')->get('form_filters', array()));
        }
    }

    /**
     * Called on POST_SEARCH_QUERY_BUILDER event.
     *
     * @param GenericEvent $event
     */
    public function onPostSearchQueryBuilder(GenericEvent $event)
    {
        $queryBuilder = $event->getArgument('query_builder');

        if ($event->hasArgument('request')) {
            $this->applyRequestFilters($queryBuilder, $event->getArgument('request')->get('filters', array()));
        }
    }

    /**
     * Applies request filters on queryBuilder.
     *
     * @param QueryBuilder $queryBuilder
     * @param array        $filters
     */
    protected function applyRequestFilters(QueryBuilder $queryBuilder, array $filters = array())
    {
        foreach ($filters as $field => $value) {
            // Empty string and numeric keys is considered as "not applied filter"
            if (\is_int($field) || '' === $value) {
                continue;
            }
            // Add root entity alias if none provided
            $field = false === \strpos($field, '.') ? $queryBuilder->getRootAlias().'.'.$field : $field;
            // Checks if filter is directly appliable on queryBuilder
            if (!$this->isFilterAppliable($queryBuilder, $field)) {
                continue;
            }
            // Sanitize parameter name
            $parameter = 'request_filter_'.\str_replace('.', '_', $field);

            $this->filterQueryBuilder($queryBuilder, $field, $parameter, $value);
        }
    }

    /**
     * Applies form filters on queryBuilder.
     *
     * @param QueryBuilder $queryBuilder
     * @param array        $filters
     */
    protected function applyFormFilters(QueryBuilder $queryBuilder, array $filters = array())
    {
        foreach ($filters as $field => $value) {
            $value = $this->filterEasyadminAutocompleteValue($value);
            // Empty string and numeric keys is considered as "not applied filter"
            if (\is_int($field) || '' === $value) {
                continue;
            }
            // Add root entity alias if none provided
            $field = false === \strpos($field, '.') ? $queryBuilder->getRootAlias().'.'.$field : $field;
            // Sanitize parameter name
            $parameter = 'form_filter_'.\str_replace('.', '_', $field);
            // Checks if filter is directly appliable on queryBuilder
            if ($this->isFilterAppliable($queryBuilder, $field)) {
                $this->filterQueryBuilder($queryBuilder, $field, $parameter, $value);
            } elseif ($this->isFilterAppliableToMany($queryBuilder, $field)) {
                $this->filterQueryBuilderToMany($queryBuilder, $field, $parameter, $value);
            } else {
                continue;
            }
        }
    }

    private function filterEasyadminAutocompleteValue($value)
    {
        if (!\is_array($value) || !isset($value['autocomplete']) || 1 !== \count($value)) {
            return $value;
        }

        return $value['autocomplete'];
    }

    /**
     * Filters queryBuilder.
     *
     * @param QueryBuilder $queryBuilder
     * @param string       $field
     * @param string       $parameter
     * @param mixed        $value
     */
    protected function filterQueryBuilder(QueryBuilder $queryBuilder, string $field, string $parameter, $value)
    {
        // For multiple value, use an IN clause, equality otherwise
        if (\is_array($value)) {
            $filterDqlPart = $field.' IN (:'.$parameter.')';
        } elseif ('_NULL' === $value) {
            $parameter = null;
            $filterDqlPart = $field.' IS NULL';
        } elseif ('_NOT_NULL' === $value) {
            $parameter = null;
            $filterDqlPart = $field.' IS NOT NULL';
        } else {
            $filterDqlPart = $field.' = :'.$parameter;
        }

        $queryBuilder->andWhere($filterDqlPart);
        if (null !== $parameter) {
            $queryBuilder->setParameter($parameter, $value);
        }
    }

    protected function filterQueryBuilderToMany(QueryBuilder $queryBuilder, string $field, string $parameter, $value)
    {
        $filterDqlPart = [];
        $filterDqlParam = [];

        // For multiple value, use an IN clause, equality otherwise
        if (\is_array($value)) {
            foreach ($value as $index => $value) {
                $tag = $parameter.'_eaeb_'.$index;
                $filterDqlPart[] = ':'.$tag.' MEMBER OF '.$field;
                $filterDqlParam[$tag] = $value;
            }
        }

        $queryBuilder->andWhere(join(' OR ', $filterDqlPart));

        foreach ($filterDqlParam as $tag => $value) {
            $queryBuilder->setParameter($tag, $value);
        }
    }

    /**
     * Checks if filter is directly appliable on queryBuilder.
     *
     * @param QueryBuilder $queryBuilder
     * @param string       $field
     *
     * @return bool
     */
    protected function isFilterAppliable(QueryBuilder $queryBuilder, string $field): bool
    {
        $qbClone = clone $queryBuilder;

        try {
            $qbClone->andWhere($field.' IS NULL');

            // Generating SQL throws a QueryException if using wrong field/association
            $qbClone->getQuery()->getSQL();
        } catch (QueryException $e) {
            return false;
        }

        return true;
    }

    protected function isFilterAppliableToMany(QueryBuilder $queryBuilder, string $field): bool
    {
        $qbClone = clone $queryBuilder;

        try {
            $qbClone->andWhere($field.' IS EMPTY');

            // Generating SQL throws a QueryException if using wrong field/association
            $qbClone->getQuery()->getSQL();
        } catch (QueryException $e) {
            // HACK Start - Remove unnecessary dump.
            // dump($qbClone);
            // HACK End

            return false;
        }

        return true;
    }
}
