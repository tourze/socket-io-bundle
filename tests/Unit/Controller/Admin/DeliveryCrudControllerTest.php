<?php

namespace SocketIoBundle\Tests\Unit\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Controller\Admin\DeliveryCrudController;
use SocketIoBundle\Entity\Delivery;

class DeliveryCrudControllerTest extends TestCase
{
    public function test_get_entity_fqcn_returns_delivery_class(): void
    {
        $this->assertSame(Delivery::class, DeliveryCrudController::getEntityFqcn());
    }

    public function test_controller_class_structure(): void
    {
        $reflection = new \ReflectionClass(DeliveryCrudController::class);
        
        $this->assertSame('SocketIoBundle\Controller\Admin\DeliveryCrudController', $reflection->getName());
        $this->assertTrue($reflection->isSubclassOf(AbstractCrudController::class));
        $this->assertFalse($reflection->isAbstract());
        $this->assertFalse($reflection->isFinal());
    }

    public function test_constructor_exists(): void
    {
        $reflection = new \ReflectionClass(DeliveryCrudController::class);
        
        $this->assertTrue($reflection->hasMethod('__construct'));
        $constructor = $reflection->getMethod('__construct');
        $this->assertSame(2, $constructor->getNumberOfParameters());
        
        $parameters = $constructor->getParameters();
        $this->assertSame('entityManager', $parameters[0]->getName());
        $this->assertSame('adminUrlGenerator', $parameters[1]->getName());
    }

    public function test_configure_methods_exist_and_are_public(): void
    {
        $reflection = new \ReflectionClass(DeliveryCrudController::class);
        
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
        $reflection = new \ReflectionMethod(DeliveryCrudController::class, 'configureCrud');
        
        $this->assertSame('configureCrud', $reflection->getName());
        $this->assertSame(1, $reflection->getNumberOfParameters());
        
        $returnType = $reflection->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('EasyCorp\Bundle\EasyAdminBundle\Config\Crud', $returnType->getName());
    }

    public function test_create_index_query_builder_method_exists(): void
    {
        $reflection = new \ReflectionClass(DeliveryCrudController::class);
        $this->assertTrue($reflection->hasMethod('createIndexQueryBuilder'));
        $this->assertTrue($reflection->getMethod('createIndexQueryBuilder')->isPublic());
    }

    public function test_retry_delivery_action_exists_and_has_correct_attributes(): void
    {
        $reflection = new \ReflectionClass(DeliveryCrudController::class);
        
        if ($reflection->hasMethod('retryDelivery')) {
            $this->assertTrue($reflection->getMethod('retryDelivery')->isPublic());
            
            $method = $reflection->getMethod('retryDelivery');
            $attributes = $method->getAttributes();
            
            $hasAdminActionAttribute = false;
            foreach ($attributes as $attribute) {
                if ($attribute->getName() === 'EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction') {
                    $hasAdminActionAttribute = true;
                    break;
                }
            }
            
            $this->assertTrue($hasAdminActionAttribute, 'retryDelivery method should have AdminAction attribute');
        } else {
            $this->markTestSkipped('retryDelivery method does not exist in this controller');
        }
    }

    public function test_view_message_action_exists_and_has_correct_attributes(): void
    {
        $reflection = new \ReflectionClass(DeliveryCrudController::class);
        
        if ($reflection->hasMethod('viewMessage')) {
            $this->assertTrue($reflection->getMethod('viewMessage')->isPublic());
            
            $method = $reflection->getMethod('viewMessage');
            $attributes = $method->getAttributes();
            
            $hasAdminActionAttribute = false;
            foreach ($attributes as $attribute) {
                if ($attribute->getName() === 'EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction') {
                    $hasAdminActionAttribute = true;
                    break;
                }
            }
            
            $this->assertTrue($hasAdminActionAttribute, 'viewMessage method should have AdminAction attribute');
        } else {
            $this->markTestSkipped('viewMessage method does not exist in this controller');
        }
    }

    public function test_configure_actions_method_returns_correct_type(): void
    {
        $reflection = new \ReflectionMethod(DeliveryCrudController::class, 'configureActions');
        
        $returnType = $reflection->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('EasyCorp\Bundle\EasyAdminBundle\Config\Actions', $returnType->getName());
    }

    public function test_configure_filters_method_returns_correct_type(): void
    {
        $reflection = new \ReflectionMethod(DeliveryCrudController::class, 'configureFilters');
        
        $returnType = $reflection->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('EasyCorp\Bundle\EasyAdminBundle\Config\Filters', $returnType->getName());
    }

    public function test_configure_fields_method_returns_iterable(): void
    {
        $reflection = new \ReflectionMethod(DeliveryCrudController::class, 'configureFields');
        
        $returnType = $reflection->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('iterable', $returnType->getName());
    }

    public function test_custom_action_methods_have_correct_signatures(): void
    {
        $reflection = new \ReflectionClass(DeliveryCrudController::class);
        
        $customMethods = ['retryDelivery', 'viewMessage'];
        
        foreach ($customMethods as $methodName) {
            if ($reflection->hasMethod($methodName)) {
                $method = $reflection->getMethod($methodName);
                $this->assertSame(1, $method->getNumberOfParameters(), "Method {$methodName} should have 1 parameter");
                $this->assertSame('context', $method->getParameters()[0]->getName(), "Method {$methodName} parameter should be named 'context'");
                
                $returnType = $method->getReturnType();
                $this->assertNotNull($returnType, "Method {$methodName} should have return type");
                $this->assertSame('Symfony\Component\HttpFoundation\Response', $returnType->getName(), "Method {$methodName} should return Response");
            }
        }
    }

    public function test_controller_has_required_properties(): void
    {
        $reflection = new \ReflectionClass(DeliveryCrudController::class);
        
        $this->assertTrue($reflection->hasProperty('entityManager'), 'Controller should have entityManager property');
        $this->assertTrue($reflection->hasProperty('adminUrlGenerator'), 'Controller should have adminUrlGenerator property');
        
        $entityManagerProperty = $reflection->getProperty('entityManager');
        $this->assertTrue($entityManagerProperty->isPrivate(), 'entityManager property should be private');
        
        $adminUrlGeneratorProperty = $reflection->getProperty('adminUrlGenerator');
        $this->assertTrue($adminUrlGeneratorProperty->isPrivate(), 'adminUrlGenerator property should be private');
    }
} 