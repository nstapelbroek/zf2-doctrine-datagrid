<?php
namespace Wms\Admin\DataGrid;

return array(
    'service_manager' => array(
        'factories' => array(
            'Wms\Admin\DataGrid\Service\QueryFilterHelper' => 'Wms\Admin\DataGrid\Factory\QueryFilterHelperFactory',
            'Wms\Admin\DataGrid\Service\SearchFilterHelper' => 'Wms\Admin\DataGrid\Factory\SearchFilterHelperFactory',
            'Wms\Admin\DataGrid\Service\QueryBuilderService' => 'Wms\Admin\DataGrid\Factory\QueryBuilderServiceFactory',
            'Wms\Admin\DataGrid\Service\EntityMetadataHelper' => 'Wms\Admin\DataGrid\Factory\EntityMetadataHelperFactory',
            'Wms\Admin\DataGrid\Service\TableBuilderService' => 'Wms\Admin\DataGrid\Factory\TableBuilderServiceFactory',
            'Wms\Admin\DataGrid\Options\ModuleOptions' => 'Wms\Admin\DataGrid\Factory\ModuleOptionsFactory',
        )
    ),
    'controllers' => array(
        'factories' => array(
            'Wms\Admin\DataGrid\Controller\DataGridController' => 'Wms\Admin\DataGrid\Factory\DataGridControllerFactory',
        ),
    ),

    'view_manager' => array(
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
        'controller_map' => array(
            __NAMESPACE__ => '/',
        ),
    ),
    'view_helpers' => array(
        'invokables' => array(
            'DataGridTable' => 'Wms\Admin\DataGrid\View\Helper\DataGridTable',
            'DataGridSearchFilter' => 'Wms\Admin\DataGrid\View\Helper\SearchFilter',
            'UrlWithQuery' => 'Wms\Admin\DataGrid\View\Helper\UrlWithQuery',
            'DataGridForm' => 'Wms\Admin\DataGrid\View\Form\Form',
            'DataGridFormRow' => 'Wms\Admin\DataGrid\View\Form\FormRow',
            'DataGridFormCollection' => 'Wms\Admin\DataGrid\View\Form\FormCollection',
            'DataGridNestedFormCollection' => 'Wms\Admin\DataGrid\View\Form\NestedFormCollection',
        ),
    ),
    'router' => array(
        'routes' => array(
            'zfcadmin' => array(
                'child_routes' => array(
                    'datagrid' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/datagrid',
                            'defaults' => array(
                                'controller'    => 'Wms\Admin\DataGrid\Controller\DataGridController',
                                'action' => 'index'
                            ),
                        ),
                    )
                )
            )
        )
    ),
);
