<?php

namespace SocketIoBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use SocketIoBundle\Entity\Delivery;
use SocketIoBundle\Enum\MessageStatus;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\HttpFoundation\Response;

class DeliveryCrudController extends AbstractCrudController
{
    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager,
    ) {
        $this->entityManager = $entityManager;
    }

    public static function getEntityFqcn(): string
    {
        return Delivery::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('投递记录')
            ->setEntityLabelInPlural('投递记录')
            ->setPageTitle('index', '投递记录列表')
            ->setPageTitle('detail', fn (Delivery $delivery) => sprintf('投递记录: %s', $delivery->getId()))
            ->setPageTitle('edit', fn (Delivery $delivery) => sprintf('编辑投递记录: %s', $delivery->getId()))
            ->setPageTitle('new', '新建投递记录')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setSearchFields(['id', 'error'])
            ->setPaginatorPageSize(20)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->setMaxLength(9999)
            ->hideOnForm();

        yield AssociationField::new('message', '消息')
            ->setFormTypeOption('choice_label', 'event')
            ->setRequired(true);

        yield AssociationField::new('socket', 'Socket连接')
            ->setFormTypeOption('choice_label', 'socketId')
            ->setRequired(true);

        yield ChoiceField::new('status', '状态')
            ->setFormType(EnumType::class)
            ->setFormTypeOptions([
                'class' => MessageStatus::class,
                'choices' => MessageStatus::cases(),
                'choice_label' => function (MessageStatus $status) {
                    return $status->label();
                }
            ])
            ->formatValue(function ($value) {
                if ($value instanceof MessageStatus) {
                    $label = $value->label();
                    $class = match ($value) {
                        MessageStatus::PENDING => 'warning',
                        MessageStatus::DELIVERED => 'success',
                        MessageStatus::FAILED => 'danger',
                    };
                    return sprintf('<span class="badge bg-%s">%s</span>', $class, $label);
                }
                return '';
            })
            ->setCustomOptions(['renderAsHtml' => true]);

        yield IntegerField::new('retries', '重试次数');

        if (Crud::PAGE_DETAIL === $pageName || Crud::PAGE_EDIT === $pageName) {
            yield TextField::new('error', '错误信息')
                ->hideOnIndex();
        }

        yield DateTimeField::new('deliveredAt', '投递时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->hideOnForm();

        yield DateTimeField::new('createTime', '创建时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->hideOnForm();

        yield DateTimeField::new('updateTime', '更新时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->hideOnForm();
    }

    public function configureFilters(Filters $filters): Filters
    {
        $statusChoices = [];
        foreach (MessageStatus::cases() as $status) {
            $statusChoices[$status->label()] = $status->value;
        }

        return $filters
            ->add(EntityFilter::new('message', '消息'))
            ->add(EntityFilter::new('socket', 'Socket连接'))
            ->add(ChoiceFilter::new('status', '状态')->setChoices($statusChoices))
            ->add(TextFilter::new('error', '错误信息'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
            ->add(DateTimeFilter::new('deliveredAt', '投递时间'));
    }

    public function configureActions(Actions $actions): Actions
    {
        $retryDelivery = Action::new('retryDelivery', '重试')
            ->linkToCrudAction('retryDelivery')
            ->setCssClass('btn btn-warning')
            ->setIcon('fa fa-redo');

        $markDelivered = Action::new('markDelivered', '标记已投递')
            ->linkToCrudAction('markDelivered')
            ->setCssClass('btn btn-success')
            ->setIcon('fa fa-check');

        $markFailed = Action::new('markFailed', '标记失败')
            ->linkToCrudAction('markFailed')
            ->setCssClass('btn btn-danger')
            ->setIcon('fa fa-times');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $retryDelivery)
            ->add(Crud::PAGE_DETAIL, $markDelivered)
            ->add(Crud::PAGE_DETAIL, $markFailed)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, Action::DELETE]);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->leftJoin('entity.message', 'message')
            ->leftJoin('entity.socket', 'socket')
            ->addSelect('message', 'socket');
    }

    /**
     * 自定义操作：重试投递
     */
    #[AdminAction('{entityId}/retry', 'retry_delivery')]
    public function retryDelivery(AdminContext $context): Response
    {
        $delivery = $context->getEntity()->getInstance();
        $delivery->incrementRetries();
        $delivery->setStatus(MessageStatus::PENDING);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('已重试投递记录 %s，当前为第%d次重试', $delivery->getId(), $delivery->getRetries()));

        return $this->redirect($context->getReferrer());
    }

    /**
     * 自定义操作：标记为已投递
     */
    #[AdminAction('{entityId}/mark-delivered', 'mark_delivered')]
    public function markDelivered(AdminContext $context): Response
    {
        $delivery = $context->getEntity()->getInstance();
        $delivery->setStatus(MessageStatus::DELIVERED);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('已将投递记录 %s 标记为已投递', $delivery->getId()));

        return $this->redirect($context->getReferrer());
    }

    /**
     * 自定义操作：标记为失败
     */
    #[AdminAction('{entityId}/mark-failed', 'mark_failed')]
    public function markFailed(AdminContext $context): Response
    {
        $delivery = $context->getEntity()->getInstance();
        $delivery->setStatus(MessageStatus::FAILED);
        if (!$delivery->getError()) {
            $delivery->setError('手动标记为失败');
        }
        $this->entityManager->flush();

        $this->addFlash('warning', sprintf('已将投递记录 %s 标记为失败', $delivery->getId()));

        return $this->redirect($context->getReferrer());
    }
} 