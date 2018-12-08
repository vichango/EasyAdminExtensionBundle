<?php

namespace AlterPHP\EasyAdminExtensionBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

abstract class AbstractPostQueryBuilderSubscriber implements EventSubscriberInterface
{
    /**
     * Called on POST_LIST_QUERY_BUILDER event.
     *
     * @param GenericEvent $event
     */
    public function onPostListQueryBuilder(GenericEvent $event)
    {
        $queryBuilder = $event->getArgument('query_builder');

        // Request filters
        if ($event->hasArgument('request')) {
            $this->applyRequestFilters($queryBuilder, $event->getArgument('request')->get('filters', array()));
        }

        // List form filters
        if ($event->hasArgument('entity')) {
            $entityConfig = $event->getArgument('entity');
            if (isset($entityConfig['list']['form_filters'])) {
                $listFormFiltersForm = $this->listFormFiltersHelper->getListFormFilters($entityConfig['list']['form_filters']);
                if ($listFormFiltersForm->isSubmitted() && $listFormFiltersForm->isValid()) {
                    $this->applyFormFilters($queryBuilder, $listFormFiltersForm->getData());
                }
            }
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
}
