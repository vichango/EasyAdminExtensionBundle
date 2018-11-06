<?php

namespace AlterPHP\EasyAdminExtensionBundle\Tests\Configuration;

use AlterPHP\EasyAdminExtensionBundle\Configuration\ListFormFiltersConfigPass;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class ListFormFiltersConfigPassTest extends \PHPUnit_Framework_TestCase
{
    public function testDefinedListFormFilters()
    {
        $doctrineOrm = $this->createMock(ManagerRegistry::class);

        $listFormFiltersConfigPass = new ListFormFiltersConfigPass($doctrineOrm);

        $backendConfig = array(
            'entities' => array(
                'TestEntity' => array(
                    'class' => 'App\\Entity\\TestEntity',
                    'list' => array('form_filters' => array(
                        'filter1' => array('type' => 'foo'),
                        'filter2' => array('type' => 'bar'),
                    )),
                ),
            ),
            'documents' => array(
                'TestDocument' => array(
                    'class' => 'App\\Document\\TestDocument',
                    'list' => array('form_filters' => array(
                        'filter1' => array('type' => 'foo'),
                        'filter2' => array('type' => 'bar'),
                    )),
                ),
            ),
        );

        $backendConfig = $listFormFiltersConfigPass->process($backendConfig);

        $expectedBackendConfig = array(
            'entities' => array(
                'TestEntity' => array(
                    'class' => 'App\\Entity\\TestEntity',
                    'list' => array('form_filters' => array(
                        'filter1' => array('type' => 'foo', 'property' => 'filter1'),
                        'filter2' => array('type' => 'bar', 'property' => 'filter2'),
                    )),
                ),
            ),
            'documents' => array(
                'TestDocument' => array(
                    'class' => 'App\\Document\\TestDocument',
                    'list' => array('form_filters' => array(
                        'filter1' => array('type' => 'foo', 'property' => 'filter1'),
                        'filter2' => array('type' => 'bar', 'property' => 'filter2'),
                    )),
                ),
            ),
        );

        $this->assertSame($backendConfig, $expectedBackendConfig);
    }
}
