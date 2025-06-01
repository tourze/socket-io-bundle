<?php

namespace SocketIoBundle\Tests\Unit\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Controller\Admin\SocketCrudController;
use SocketIoBundle\Entity\Socket;

class SocketCrudControllerTest extends TestCase
{
    public function test_extends_abstract_crud_controller(): void
    {
        $reflection = new \ReflectionClass(SocketCrudController::class);
        $this->assertTrue($reflection->isSubclassOf(AbstractCrudController::class));
    }

    public function test_get_entity_fqcn_returns_socket_class(): void
    {
        $this->assertSame(Socket::class, SocketCrudController::getEntityFqcn());
    }

    public function test_controller_class_structure(): void
    {
        $reflection = new \ReflectionClass(SocketCrudController::class);
        
        $this->assertSame('SocketIoBundle\Controller\Admin\SocketCrudController', $reflection->getName());
        $this->assertTrue($reflection->isSubclassOf(AbstractCrudController::class));
        $this->assertFalse($reflection->isAbstract());
        $this->assertFalse($reflection->isFinal());
    }

    public function test_constructor_exists(): void
    {
        $reflection = new \ReflectionClass(SocketCrudController::class);
        
        $this->assertTrue($reflection->hasMethod('__construct'));
        $constructor = $reflection->getMethod('__construct');
        $this->assertSame(2, $constructor->getNumberOfParameters());
        
        $parameters = $constructor->getParameters();
        $this->assertSame('entityManager', $parameters[0]->getName());
        $this->assertSame('adminUrlGenerator', $parameters[1]->getName());
    }

    public function test_configure_methods_exist_and_are_public(): void
    {
        $reflection = new \ReflectionClass(SocketCrudController::class);
        
        $this->assertTrue($reflection->hasMethod('configureCrud'));
        $this->assertTrue($reflection->hasMethod('configureFields'));
        $this->assertTrue($reflection->hasMethod('configureFilters'));
        $this->assertTrue($reflection->hasMethod('configureActions'));
        
        $this->assertTrue($reflection->getMethod('configureCrud')->isPublic());
        $this->assertTrue($reflection->getMethod('configureFields')->isPublic());
        $this->assertTrue($reflection->getMethod('configureFilters')->isPublic());
        $this->assertTrue($reflection->getMethod('configureActions')->isPublic());
    }

    public function test_create_index_query_builder_method_exists(): void
    {
        $reflection = new \ReflectionClass(SocketCrudController::class);
        $this->assertTrue($reflection->hasMethod('createIndexQueryBuilder'));
        $this->assertTrue($reflection->getMethod('createIndexQueryBuilder')->isPublic());
    }

    public function test_view_rooms_action_exists_and_has_correct_attributes(): void
    {
        $reflection = new \ReflectionClass(SocketCrudController::class);
        
        $this->assertTrue($reflection->hasMethod('viewRooms'));
        $this->assertTrue($reflection->getMethod('viewRooms')->isPublic());
        
        $method = $reflection->getMethod('viewRooms');
        $attributes = $method->getAttributes();
        
        $adminActionAttribute = null;
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === 'EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction') {
                $adminActionAttribute = $attribute;
                break;
            }
        }
        
        $this->assertNotNull($adminActionAttribute);
        $this->assertSame('{entityId}/view-rooms', $adminActionAttribute->getArguments()[0]);
        $this->assertSame('view_rooms', $adminActionAttribute->getArguments()[1]);
    }

    public function test_disconnect_socket_action_exists_and_has_correct_attributes(): void
    {
        $reflection = new \ReflectionClass(SocketCrudController::class);
        
        $this->assertTrue($reflection->hasMethod('disconnectSocket'));
        $this->assertTrue($reflection->getMethod('disconnectSocket')->isPublic());
        
        $method = $reflection->getMethod('disconnectSocket');
        $attributes = $method->getAttributes();
        
        $adminActionAttribute = null;
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === 'EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction') {
                $adminActionAttribute = $attribute;
                break;
            }
        }
        
        $this->assertNotNull($adminActionAttribute);
        $this->assertSame('{entityId}/disconnect', $adminActionAttribute->getArguments()[0]);
        $this->assertSame('disconnect_socket', $adminActionAttribute->getArguments()[1]);
    }

    public function test_refresh_status_action_exists_and_has_correct_attributes(): void
    {
        $reflection = new \ReflectionClass(SocketCrudController::class);
        
        $this->assertTrue($reflection->hasMethod('refreshStatus'));
        $this->assertTrue($reflection->getMethod('refreshStatus')->isPublic());
        
        $method = $reflection->getMethod('refreshStatus');
        $attributes = $method->getAttributes();
        
        $adminActionAttribute = null;
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === 'EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction') {
                $adminActionAttribute = $attribute;
                break;
            }
        }
        
        $this->assertNotNull($adminActionAttribute);
        $this->assertSame('{entityId}/refresh-status', $adminActionAttribute->getArguments()[0]);
        $this->assertSame('refresh_status', $adminActionAttribute->getArguments()[1]);
    }

    public function test_custom_action_methods_have_correct_signatures(): void
    {
        $reflection = new \ReflectionClass(SocketCrudController::class);
        
        // viewRooms method
        $viewRoomsMethod = $reflection->getMethod('viewRooms');
        $this->assertSame(1, $viewRoomsMethod->getNumberOfParameters());
        $this->assertSame('context', $viewRoomsMethod->getParameters()[0]->getName());
        $this->assertSame('Symfony\Component\HttpFoundation\Response', $viewRoomsMethod->getReturnType()->getName());
        
        // disconnectSocket method
        $disconnectMethod = $reflection->getMethod('disconnectSocket');
        $this->assertSame(1, $disconnectMethod->getNumberOfParameters());
        $this->assertSame('context', $disconnectMethod->getParameters()[0]->getName());
        $this->assertSame('Symfony\Component\HttpFoundation\Response', $disconnectMethod->getReturnType()->getName());
        
        // refreshStatus method
        $refreshMethod = $reflection->getMethod('refreshStatus');
        $this->assertSame(1, $refreshMethod->getNumberOfParameters());
        $this->assertSame('context', $refreshMethod->getParameters()[0]->getName());
        $this->assertSame('Symfony\Component\HttpFoundation\Response', $refreshMethod->getReturnType()->getName());
    }

    public function test_configure_filters_method_signature(): void
    {
        $reflection = new \ReflectionMethod(SocketCrudController::class, 'configureFilters');
        
        $this->assertSame('configureFilters', $reflection->getName());
        $this->assertSame(1, $reflection->getNumberOfParameters());
        $this->assertSame(1, $reflection->getNumberOfRequiredParameters());
        
        $returnType = $reflection->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('EasyCorp\Bundle\EasyAdminBundle\Config\Filters', $returnType->getName());
    }

    public function test_configure_actions_method_signature(): void
    {
        $reflection = new \ReflectionMethod(SocketCrudController::class, 'configureActions');
        
        $this->assertSame('configureActions', $reflection->getName());
        $this->assertSame(1, $reflection->getNumberOfParameters());
        $this->assertSame(1, $reflection->getNumberOfRequiredParameters());
        
        $returnType = $reflection->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('EasyCorp\Bundle\EasyAdminBundle\Config\Actions', $returnType->getName());
    }

    public function test_configure_crud_method_signature(): void
    {
        $reflection = new \ReflectionMethod(SocketCrudController::class, 'configureCrud');
        
        $this->assertSame('configureCrud', $reflection->getName());
        $this->assertSame(1, $reflection->getNumberOfParameters());
        $this->assertSame(1, $reflection->getNumberOfRequiredParameters());
        
        $returnType = $reflection->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('EasyCorp\Bundle\EasyAdminBundle\Config\Crud', $returnType->getName());
    }

    public function test_configure_fields_method_signature(): void
    {
        $reflection = new \ReflectionMethod(SocketCrudController::class, 'configureFields');
        
        $this->assertSame('configureFields', $reflection->getName());
        $this->assertSame(1, $reflection->getNumberOfParameters());
        $this->assertSame(1, $reflection->getNumberOfRequiredParameters());
        
        $returnType = $reflection->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('iterable', $returnType->getName());
    }

    public function test_docblock_comments_exist_for_custom_actions(): void
    {
        $reflection = new \ReflectionClass(SocketCrudController::class);
        
        // 验证自定义动作方法都有注释
        $customActionMethods = [
            'viewRooms' => '自定义操作：查看Socket房间',
            'disconnectSocket' => '自定义操作：断开Socket连接',
            'refreshStatus' => '自定义操作：刷新连接状态'
        ];
        
        foreach ($customActionMethods as $methodName => $expectedComment) {
            $method = $reflection->getMethod($methodName);
            $docComment = $method->getDocComment();
            
            $this->assertNotFalse($docComment, "Method {$methodName} should have docblock comment");
            $this->assertStringContainsString('自定义操作', $docComment, "Method {$methodName} docblock should contain expected comment pattern");
        }
    }

    public function test_controller_has_required_properties(): void
    {
        $reflection = new \ReflectionClass(SocketCrudController::class);
        
        $this->assertTrue($reflection->hasProperty('entityManager'), 'Controller should have entityManager property');
        $this->assertTrue($reflection->hasProperty('adminUrlGenerator'), 'Controller should have adminUrlGenerator property');
        
        $entityManagerProperty = $reflection->getProperty('entityManager');
        $this->assertTrue($entityManagerProperty->isPrivate(), 'entityManager property should be private');
        
        $adminUrlGeneratorProperty = $reflection->getProperty('adminUrlGenerator');
        $this->assertTrue($adminUrlGeneratorProperty->isPrivate(), 'adminUrlGenerator property should be private');
    }
} 