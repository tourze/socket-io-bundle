<?php

namespace SocketIoBundle\Tests\Unit\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Controller\Admin\MessageCrudController;
use SocketIoBundle\Entity\Message;

class MessageCrudControllerTest extends TestCase
{
    public function test_get_entity_fqcn_returns_message_class(): void
    {
        $this->assertSame(Message::class, MessageCrudController::getEntityFqcn());
    }

    public function test_controller_class_structure(): void
    {
        $reflection = new \ReflectionClass(MessageCrudController::class);
        
        $this->assertSame('SocketIoBundle\Controller\Admin\MessageCrudController', $reflection->getName());
        $this->assertTrue($reflection->isSubclassOf(AbstractCrudController::class));
        $this->assertFalse($reflection->isAbstract());
        $this->assertFalse($reflection->isFinal());
    }

    public function test_constructor_exists(): void
    {
        $reflection = new \ReflectionClass(MessageCrudController::class);
        
        $this->assertTrue($reflection->hasMethod('__construct'));
        $constructor = $reflection->getMethod('__construct');
        $this->assertSame(2, $constructor->getNumberOfParameters());
        
        $parameters = $constructor->getParameters();
        $this->assertSame('entityManager', $parameters[0]->getName());
        $this->assertSame('adminUrlGenerator', $parameters[1]->getName());
    }

    public function test_configure_methods_exist_and_are_public(): void
    {
        $reflection = new \ReflectionClass(MessageCrudController::class);
        
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
        $reflection = new \ReflectionMethod(MessageCrudController::class, 'configureCrud');
        
        $this->assertSame('configureCrud', $reflection->getName());
        $this->assertSame(1, $reflection->getNumberOfParameters());
        
        $returnType = $reflection->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('EasyCorp\Bundle\EasyAdminBundle\Config\Crud', $returnType->getName());
    }

    public function test_create_index_query_builder_method_exists(): void
    {
        $reflection = new \ReflectionClass(MessageCrudController::class);
        $this->assertTrue($reflection->hasMethod('createIndexQueryBuilder'));
        $this->assertTrue($reflection->getMethod('createIndexQueryBuilder')->isPublic());
    }

    public function test_view_deliveries_action_exists_and_has_correct_attributes(): void
    {
        $reflection = new \ReflectionClass(MessageCrudController::class);
        
        $this->assertTrue($reflection->hasMethod('viewDeliveries'));
        $this->assertTrue($reflection->getMethod('viewDeliveries')->isPublic());
        
        $method = $reflection->getMethod('viewDeliveries');
        $attributes = $method->getAttributes();
        
        $adminActionAttribute = null;
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === 'EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction') {
                $adminActionAttribute = $attribute;
                break;
            }
        }
        
        $this->assertNotNull($adminActionAttribute);
        $this->assertSame('{entityId}/view-deliveries', $adminActionAttribute->getArguments()[0]);
        $this->assertSame('view_deliveries', $adminActionAttribute->getArguments()[1]);
    }

    public function test_resend_message_action_exists_and_has_correct_attributes(): void
    {
        $reflection = new \ReflectionClass(MessageCrudController::class);
        
        $this->assertTrue($reflection->hasMethod('resendMessage'));
        $this->assertTrue($reflection->getMethod('resendMessage')->isPublic());
        
        $method = $reflection->getMethod('resendMessage');
        $attributes = $method->getAttributes();
        
        $adminActionAttribute = null;
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === 'EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction') {
                $adminActionAttribute = $attribute;
                break;
            }
        }
        
        $this->assertNotNull($adminActionAttribute);
        $this->assertSame('{entityId}/resend', $adminActionAttribute->getArguments()[0]);
        $this->assertSame('resend_message', $adminActionAttribute->getArguments()[1]);
    }

    public function test_custom_action_methods_have_correct_signatures(): void
    {
        $reflection = new \ReflectionClass(MessageCrudController::class);
        
        // viewDeliveries method
        $viewDeliveriesMethod = $reflection->getMethod('viewDeliveries');
        $this->assertSame(1, $viewDeliveriesMethod->getNumberOfParameters());
        $this->assertSame('context', $viewDeliveriesMethod->getParameters()[0]->getName());
        $this->assertSame('Symfony\Component\HttpFoundation\Response', $viewDeliveriesMethod->getReturnType()->getName());
        
        // resendMessage method
        $resendMethod = $reflection->getMethod('resendMessage');
        $this->assertSame(1, $resendMethod->getNumberOfParameters());
        $this->assertSame('context', $resendMethod->getParameters()[0]->getName());
        $this->assertSame('Symfony\Component\HttpFoundation\Response', $resendMethod->getReturnType()->getName());
    }

    public function test_docblock_comments_exist_for_custom_actions(): void
    {
        $reflection = new \ReflectionClass(MessageCrudController::class);
        
        // 验证自定义动作方法都有注释
        $customActionMethods = [
            'viewDeliveries' => '自定义操作：查看投递记录',
            'resendMessage' => '自定义操作：重新发送消息'
        ];
        
        foreach ($customActionMethods as $methodName => $expectedComment) {
            $method = $reflection->getMethod($methodName);
            $docComment = $method->getDocComment();
            
            $this->assertNotFalse($docComment, "Method {$methodName} should have docblock comment");
            $this->assertStringContainsString($expectedComment, $docComment, "Method {$methodName} docblock should contain expected comment");
        }
    }

    public function test_configure_actions_method_returns_correct_type(): void
    {
        $reflection = new \ReflectionMethod(MessageCrudController::class, 'configureActions');
        
        $returnType = $reflection->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('EasyCorp\Bundle\EasyAdminBundle\Config\Actions', $returnType->getName());
    }

    public function test_configure_filters_method_returns_correct_type(): void
    {
        $reflection = new \ReflectionMethod(MessageCrudController::class, 'configureFilters');
        
        $returnType = $reflection->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('EasyCorp\Bundle\EasyAdminBundle\Config\Filters', $returnType->getName());
    }

    public function test_configure_fields_method_returns_iterable(): void
    {
        $reflection = new \ReflectionMethod(MessageCrudController::class, 'configureFields');
        
        $returnType = $reflection->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('iterable', $returnType->getName());
    }
} 