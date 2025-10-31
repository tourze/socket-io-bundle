<?php

namespace SocketIoBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use SocketIoBundle\Entity\Message;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractCrudController<Message>
 */
#[AdminCrud(routePath: '/socket-io/message', routeName: 'socket_io_message')]
final class MessageCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Message::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('消息')
            ->setEntityLabelInPlural('消息')
            ->setPageTitle('index', '消息列表')
            ->setPageTitle('detail', fn (Message $message) => sprintf('消息: %s', $message->getEvent()))
            ->setPageTitle('edit', fn (Message $message) => sprintf('编辑消息: %s', $message->getEvent()))
            ->setPageTitle('new', '新建消息')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setSearchFields(['id', 'event'])
            ->setPaginatorPageSize(20)
            ->showEntityActionsInlined()
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->setMaxLength(9999)
            ->hideOnForm()
        ;

        yield TextField::new('event', '事件名')
            ->setHelp('Socket.IO 事件名称')
        ;

        yield CodeEditorField::new('data', '数据')
            ->setLanguage('javascript')
            ->setHelp('消息数据，JSON 格式')
            ->formatValue(function ($value) {
                return is_array($value) ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $value;
            })
            ->setFormTypeOption('data_class', null)
            ->setFormTypeOption('empty_data', '{}')
        ;

        if (Crud::PAGE_DETAIL === $pageName || Crud::PAGE_NEW === $pageName || Crud::PAGE_EDIT === $pageName) {
            yield CodeEditorField::new('metadata', '元数据')
                ->setLanguage('javascript')
                ->setHelp('消息的附加元数据，JSON 格式')
                ->hideOnIndex()
                ->formatValue(function ($value) {
                    return is_array($value) ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $value;
                })
                ->setFormTypeOption('data_class', null)
                ->setFormTypeOption('empty_data', 'null')
            ;
        }

        yield AssociationField::new('sender', '发送者')
            ->setFormTypeOption('choice_label', 'socketId')
            ->hideOnIndex()
        ;

        if (Crud::PAGE_DETAIL === $pageName || Crud::PAGE_NEW === $pageName || Crud::PAGE_EDIT === $pageName) {
            yield AssociationField::new('rooms', '目标房间')
                ->setFormTypeOption('by_reference', false)
                ->setRequired(false)
            ;
        }

        if (Crud::PAGE_DETAIL === $pageName) {
            yield AssociationField::new('deliveries', '投递记录')
                ->hideOnForm()
                ->hideOnIndex()
            ;
        }

        yield DateTimeField::new('createTime', '创建时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->hideOnForm()
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('event', '事件名'))
            ->add(EntityFilter::new('rooms', '房间'))
            ->add(EntityFilter::new('sender', '发送者'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $viewDeliveries = Action::new('viewDeliveries', '查看投递')
            ->linkToCrudAction('viewDeliveries')
            ->setCssClass('btn btn-info')
            ->setIcon('fa fa-paper-plane')
        ;

        $resendMessage = Action::new('resendMessage', '重新发送')
            ->linkToCrudAction('resendMessage')
            ->setCssClass('btn btn-warning')
            ->setIcon('fa fa-redo')
        ;

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $viewDeliveries)
            ->add(Crud::PAGE_DETAIL, $resendMessage)
        ;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->leftJoin('entity.rooms', 'rooms')
            ->leftJoin('entity.sender', 'sender')
            ->addSelect('rooms', 'sender')
        ;
    }

    /**
     * 自定义操作：查看投递记录
     */
    #[AdminAction(routePath: '{id}/view-deliveries', routeName: 'view_deliveries')]
    public function viewDeliveries(AdminContext $context): Response
    {
        $message = $context->getEntity()->getInstance();
        if (!$message instanceof Message) {
            throw $this->createNotFoundException('消息不存在');
        }

        // 重定向到投递记录列表，并添加过滤条件
        $url = $this->adminUrlGenerator
            ->setController(DeliveryCrudController::class)
            ->setAction(Action::INDEX)
            ->set('filters', [
                'message' => ['comparison' => '=', 'value' => $message->getId()],
            ])
            ->generateUrl()
        ;

        return $this->redirect($url);
    }

    /**
     * 自定义操作：重新发送消息
     */
    #[AdminAction(routePath: '{id}/resend', routeName: 'resend_message')]
    public function resendMessage(AdminContext $context): Response
    {
        $message = $context->getEntity()->getInstance();
        if (!$message instanceof Message) {
            throw $this->createNotFoundException('消息不存在');
        }

        // 这里应该调用一个服务来重新发送消息
        // 为了示例，我们仅仅显示一个提示信息
        $this->addFlash('warning', sprintf('消息"%s"重新发送功能需要实现相关服务', $message->getEvent()));

        $referer = $context->getRequest()->headers->get('referer');

        return $this->redirect($referer ?? $this->generateUrl('admin'));
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Message) {
            $this->processJsonFields($entityInstance);
        }
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Message) {
            $this->processJsonFields($entityInstance);
        }
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function processJsonFields(Message $message): void
    {
        // getData() 和 getMetadata() 都返回数组类型，无需处理
        // 这个方法保留为空，以防将来需要JSON字段处理逻辑
    }
}
