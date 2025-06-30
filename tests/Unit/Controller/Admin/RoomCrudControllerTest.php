<?php

namespace SocketIoBundle\Tests\Unit\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Controller\Admin\RoomCrudController;
use SocketIoBundle\Entity\Room;

class RoomCrudControllerTest extends TestCase
{
    public function test_get_entity_fqcn_returns_room_class(): void
    {
        $this->assertSame(Room::class, RoomCrudController::getEntityFqcn());
    }

    public function test_controller_class_structure(): void
    {
        $reflection = new \ReflectionClass(RoomCrudController::class);
        
        $this->assertSame('SocketIoBundle\Controller\Admin\RoomCrudController', $reflection->getName());
        $this->assertTrue($reflection->isSubclassOf(AbstractCrudController::class));
        $this->assertFalse($reflection->isAbstract());
        $this->assertFalse($reflection->isFinal());
    }

    public function test_constructor_exists(): void
    {
        $reflection = new \ReflectionClass(RoomCrudController::class);
        
        $this->assertTrue($reflection->hasMethod('__construct'));
        $constructor = $reflection->getMethod('__construct');
        $this->assertSame(1, $constructor->getNumberOfParameters());
        
        $parameters = $constructor->getParameters();
        $this->assertSame('adminUrlGenerator', $parameters[0]->getName());
    }

    public function test_configure_methods_exist_and_are_public(): void
    {
        $reflection = new \ReflectionClass(RoomCrudController::class);
        
        $this->assertTrue($reflection->hasMethod('configureCrud'));
        $this->assertTrue($reflection->hasMethod('configureFields'));
        $this->assertTrue($reflection->hasMethod('configureFilters'));
        $this->assertTrue($reflection->hasMethod('configureActions'));
        
        $this->assertTrue($reflection->getMethod('configureCrud')->isPublic());
        $this->assertTrue($reflection->getMethod('configureFields')->isPublic());
        $this->assertTrue($reflection->getMethod('configureFilters')->isPublic());
        $this->assertTrue($reflection->getMethod('configureActions')->isPublic());
    }

    public function test_configure_crud_method_signature(): void
    {
        $reflection = new \ReflectionMethod(RoomCrudController::class, 'configureCrud');
        
        $this->assertSame('configureCrud', $reflection->getName());
        $this->assertSame(1, $reflection->getNumberOfParameters());
        $this->assertSame(1, $reflection->getNumberOfRequiredParameters());
        
        $returnType = $reflection->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('EasyCorp\Bundle\EasyAdminBundle\Config\Crud', $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
    }

    public function test_configure_fields_method_signature(): void
    {
        $reflection = new \ReflectionMethod(RoomCrudController::class, 'configureFields');
        
        $this->assertSame('configureFields', $reflection->getName());
        $this->assertSame(1, $reflection->getNumberOfParameters());
        $this->assertSame(1, $reflection->getNumberOfRequiredParameters());
        
        $returnType = $reflection->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('iterable', $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
    }

    public function test_configure_filters_method_signature(): void
    {
        $reflection = new \ReflectionMethod(RoomCrudController::class, 'configureFilters');
        
        $this->assertSame('configureFilters', $reflection->getName());
        $this->assertSame(1, $reflection->getNumberOfParameters());
        $this->assertSame(1, $reflection->getNumberOfRequiredParameters());
        
        $returnType = $reflection->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('EasyCorp\Bundle\EasyAdminBundle\Config\Filters', $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
    }

    public function test_configure_actions_method_signature(): void
    {
        $reflection = new \ReflectionMethod(RoomCrudController::class, 'configureActions');
        
        $this->assertSame('configureActions', $reflection->getName());
        $this->assertSame(1, $reflection->getNumberOfParameters());
        $this->assertSame(1, $reflection->getNumberOfRequiredParameters());
        
        $returnType = $reflection->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('EasyCorp\Bundle\EasyAdminBundle\Config\Actions', $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
    }

    public function test_create_index_query_builder_method_exists(): void
    {
        $reflection = new \ReflectionClass(RoomCrudController::class);
        $this->assertTrue($reflection->hasMethod('createIndexQueryBuilder'));
        $this->assertTrue($reflection->getMethod('createIndexQueryBuilder')->isPublic());
    }

    public function test_view_sockets_action_exists_and_has_correct_attributes(): void
    {
        $reflection = new \ReflectionClass(RoomCrudController::class);
        
        $this->assertTrue($reflection->hasMethod('viewSockets'));
        $this->assertTrue($reflection->getMethod('viewSockets')->isPublic());
        
        $method = $reflection->getMethod('viewSockets');
        $attributes = $method->getAttributes();
        
        $adminActionAttribute = null;
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === 'EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction') {
                $adminActionAttribute = $attribute;
                break;
            }
        }
        
        $this->assertNotNull($adminActionAttribute);
        $instance = $adminActionAttribute->newInstance();
        $this->assertSame('{id}/view-sockets', $instance->routePath);
        $this->assertSame('view_sockets', $instance->routeName);
    }

    public function test_view_messages_action_exists_and_has_correct_attributes(): void
    {
        $reflection = new \ReflectionClass(RoomCrudController::class);
        
        $this->assertTrue($reflection->hasMethod('viewMessages'));
        $this->assertTrue($reflection->getMethod('viewMessages')->isPublic());
        
        $method = $reflection->getMethod('viewMessages');
        $attributes = $method->getAttributes();
        
        $adminActionAttribute = null;
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === 'EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction') {
                $adminActionAttribute = $attribute;
                break;
            }
        }
        
        $this->assertNotNull($adminActionAttribute);
        $instance = $adminActionAttribute->newInstance();
        $this->assertSame('{id}/view-messages', $instance->routePath);
        $this->assertSame('view_messages', $instance->routeName);
    }

    public function test_broadcast_message_form_action_exists_and_has_correct_attributes(): void
    {
        $reflection = new \ReflectionClass(RoomCrudController::class);
        
        $this->assertTrue($reflection->hasMethod('broadcastMessageForm'));
        $this->assertTrue($reflection->getMethod('broadcastMessageForm')->isPublic());
        
        $method = $reflection->getMethod('broadcastMessageForm');
        $attributes = $method->getAttributes();
        
        $adminActionAttribute = null;
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === 'EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction') {
                $adminActionAttribute = $attribute;
                break;
            }
        }
        
        $this->assertNotNull($adminActionAttribute);
        $instance = $adminActionAttribute->newInstance();
        $this->assertSame('{id}/broadcast', $instance->routePath);
        $this->assertSame('broadcast_message_form', $instance->routeName);
    }

    public function test_custom_action_methods_have_correct_signatures(): void
    {
        $reflection = new \ReflectionClass(RoomCrudController::class);
        
        // viewSockets method
        $viewSocketsMethod = $reflection->getMethod('viewSockets');
        $this->assertSame(1, $viewSocketsMethod->getNumberOfParameters());
        $this->assertSame('context', $viewSocketsMethod->getParameters()[0]->getName());
        $returnType = $viewSocketsMethod->getReturnType();
        $this->assertSame('Symfony\Component\HttpFoundation\Response', $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
        
        // viewMessages method
        $viewMessagesMethod = $reflection->getMethod('viewMessages');
        $this->assertSame(1, $viewMessagesMethod->getNumberOfParameters());
        $this->assertSame('context', $viewMessagesMethod->getParameters()[0]->getName());
        $returnType = $viewMessagesMethod->getReturnType();
        $this->assertSame('Symfony\Component\HttpFoundation\Response', $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
        
        // broadcastMessageForm method
        $broadcastMethod = $reflection->getMethod('broadcastMessageForm');
        $this->assertSame(1, $broadcastMethod->getNumberOfParameters());
        $this->assertSame('context', $broadcastMethod->getParameters()[0]->getName());
        $returnType = $broadcastMethod->getReturnType();
        $this->assertSame('Symfony\Component\HttpFoundation\Response', $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
    }

    public function test_controller_implements_admin_action_pattern(): void
    {
        $reflection = new \ReflectionClass(RoomCrudController::class);
        
        // 验证每个自定义动作方法都有AdminAction属性
        $customActionMethods = ['viewSockets', 'viewMessages', 'broadcastMessageForm'];
        
        foreach ($customActionMethods as $methodName) {
            $method = $reflection->getMethod($methodName);
            $attributes = $method->getAttributes();
            
            $hasAdminActionAttribute = false;
            foreach ($attributes as $attribute) {
                if ($attribute->getName() === 'EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction') {
                    $hasAdminActionAttribute = true;
                    break;
                }
            }
            
            $this->assertTrue($hasAdminActionAttribute, "Method {$methodName} should have AdminAction attribute");
        }
    }

    public function test_docblock_comments_exist_for_custom_actions(): void
    {
        $reflection = new \ReflectionClass(RoomCrudController::class);
        
        // 验证自定义动作方法都有注释
        $customActionMethods = [
            'viewSockets' => '自定义操作：查看Socket连接',
            'viewMessages' => '自定义操作：查看消息',
            'broadcastMessageForm' => '自定义操作：广播消息表单页面'
        ];
        
        foreach ($customActionMethods as $methodName => $expectedComment) {
            $method = $reflection->getMethod($methodName);
            $docComment = $method->getDocComment();
            
            $this->assertNotFalse($docComment, "Method {$methodName} should have docblock comment");
            $this->assertStringContainsString($expectedComment, $docComment, "Method {$methodName} docblock should contain expected comment");
        }
    }
} 