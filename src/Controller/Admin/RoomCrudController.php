<?php

namespace SocketIoBundle\Controller\Admin;

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
use SocketIoBundle\Entity\Room;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractCrudController<Room>
 */
#[AdminCrud(routePath: '/socket-io/room', routeName: 'socket_io_room')]
final class RoomCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Room::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('房间')
            ->setEntityLabelInPlural('房间')
            ->setPageTitle('index', '房间列表')
            ->setPageTitle('detail', fn (Room $room) => sprintf('房间: %s (%s)', $room->getName(), $room->getNamespace()))
            ->setPageTitle('edit', fn (Room $room) => sprintf('编辑房间: %s', $room->getName()))
            ->setPageTitle('new', '新建房间')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setSearchFields(['id', 'name', 'namespace'])
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

        yield TextField::new('name', '房间名')
            ->setHelp('Socket.IO 房间的名称')
        ;

        yield TextField::new('namespace', '命名空间')
            ->setHelp('Socket.IO 命名空间，默认为 "/"')
        ;

        if (Crud::PAGE_DETAIL === $pageName || Crud::PAGE_EDIT === $pageName) {
            yield CodeEditorField::new('metadata', '元数据')
                ->setLanguage('javascript')
                ->setHelp('房间的附加元数据，JSON 格式')
                ->hideOnIndex()
            ;
        }

        if (Crud::PAGE_DETAIL === $pageName) {
            yield AssociationField::new('sockets', '连接的Socket')
                ->setFormTypeOption('by_reference', false)
                ->hideOnIndex()
            ;

            yield AssociationField::new('messages', '关联消息')
                ->setFormTypeOption('by_reference', false)
                ->hideOnIndex()
            ;
        }

        yield DateTimeField::new('createTime', '创建时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->hideOnForm()
        ;

        yield DateTimeField::new('updateTime', '更新时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->hideOnForm()
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name', '房间名'))
            ->add(TextFilter::new('namespace', '命名空间'))
            ->add(EntityFilter::new('sockets', 'Socket连接'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
            ->add(DateTimeFilter::new('updateTime', '更新时间'))
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $viewSockets = Action::new('viewSockets', '查看连接')
            ->linkToCrudAction('viewSockets')
            ->setCssClass('btn btn-info')
            ->setIcon('fa fa-plug')
        ;

        $viewMessages = Action::new('viewMessages', '查看消息')
            ->linkToCrudAction('viewMessages')
            ->setCssClass('btn btn-primary')
            ->setIcon('fa fa-comments')
        ;

        $broadcastMessage = Action::new('broadcastMessage', '广播消息')
            ->linkToCrudAction('broadcastMessageForm')
            ->setCssClass('btn btn-success')
            ->setIcon('fa fa-bullhorn')
        ;

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $viewSockets)
            ->add(Crud::PAGE_DETAIL, $viewMessages)
            ->add(Crud::PAGE_DETAIL, $broadcastMessage)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL])
        ;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->leftJoin('entity.sockets', 'sockets')
            ->addSelect('COUNT(sockets.id) as HIDDEN socketsCount')
            ->groupBy('entity.id')
        ;
    }

    /**
     * 自定义操作：查看Socket连接
     */
    #[AdminAction(routePath: '{entityId}/view-sockets', routeName: 'view_sockets')]
    public function viewSockets(AdminContext $context): Response
    {
        $room = $context->getEntity()->getInstance();
        if (!$room instanceof Room) {
            throw $this->createNotFoundException('房间不存在');
        }

        // 重定向到Socket列表，并添加过滤条件
        $url = $this->adminUrlGenerator
            ->setController(SocketCrudController::class)
            ->setAction(Action::INDEX)
            ->set('filters', [
                'rooms' => ['comparison' => '=', 'value' => $room->getId()],
            ])
            ->generateUrl()
        ;

        return $this->redirect($url);
    }

    /**
     * 自定义操作：查看消息
     */
    #[AdminAction(routePath: '{entityId}/view-messages', routeName: 'view_messages')]
    public function viewMessages(AdminContext $context): Response
    {
        $room = $context->getEntity()->getInstance();
        if (!$room instanceof Room) {
            throw $this->createNotFoundException('房间不存在');
        }

        // 重定向到消息列表，并添加过滤条件
        $url = $this->adminUrlGenerator
            ->setController(MessageCrudController::class)
            ->setAction(Action::INDEX)
            ->set('filters', [
                'rooms' => ['comparison' => '=', 'value' => $room->getId()],
            ])
            ->generateUrl()
        ;

        return $this->redirect($url);
    }

    /**
     * 自定义操作：广播消息表单页面
     */
    #[AdminAction(routePath: '{entityId}/broadcast', routeName: 'broadcast_message_form')]
    public function broadcastMessageForm(AdminContext $context): Response
    {
        // 这里应该返回一个表单页面，但为简单起见，我们直接重定向到消息创建页面
        $url = $this->adminUrlGenerator
            ->setController(MessageCrudController::class)
            ->setAction(Action::NEW)
            ->generateUrl()
        ;

        $this->addFlash('info', '请在创建消息后，将房间关联到该消息');

        return $this->redirect($url);
    }
}
