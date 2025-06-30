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
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use SocketIoBundle\Entity\Socket;
use Symfony\Component\HttpFoundation\Response;

class SocketCrudController extends AbstractCrudController
{
    private EntityManagerInterface $entityManager;
    private AdminUrlGenerator $adminUrlGenerator;

    public function __construct(
        EntityManagerInterface $entityManager,
        AdminUrlGenerator $adminUrlGenerator,
    ) {
        $this->entityManager = $entityManager;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public static function getEntityFqcn(): string
    {
        return Socket::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Socket连接')
            ->setEntityLabelInPlural('Socket连接')
            ->setPageTitle('index', 'Socket连接列表')
            ->setPageTitle('detail', fn (Socket $socket) => sprintf('Socket连接: %s', $socket->getSocketId()))
            ->setPageTitle('edit', fn (Socket $socket) => sprintf('编辑Socket连接: %s', $socket->getSocketId()))
            ->setPageTitle('new', '新建Socket连接')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setSearchFields(['id', 'socketId', 'sessionId', 'clientId', 'namespace', 'transport'])
            ->setPaginatorPageSize(20)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->setMaxLength(9999)
            ->hideOnForm();

        yield TextField::new('socketId', 'Socket ID')
            ->setHelp('Socket.IO 连接的唯一标识符');

        yield TextField::new('sessionId', '会话ID')
            ->setHelp('会话的唯一标识符');

        yield TextField::new('clientId', '客户端ID')
            ->setRequired(false)
            ->setHelp('客户端自定义标识符（可选）');

        yield TextField::new('namespace', '命名空间')
            ->setHelp('Socket.IO 命名空间，默认为 "/"');

        yield TextField::new('transport', '传输类型')
            ->setHelp('连接的传输类型，如 polling、websocket 等');

        yield BooleanField::new('connected', '是否在线')
            ->renderAsSwitch(true);

        yield IntegerField::new('pollCount', '轮询次数')
            ->setHelp('长轮询连接的轮询请求次数');

        if (Crud::PAGE_DETAIL === $pageName) {
            yield CodeEditorField::new('handshake', '握手数据')
                ->setLanguage('json')
                ->hideOnIndex();

            yield AssociationField::new('rooms', '加入的房间')
                ->setFormTypeOption('by_reference', false)
                ->hideOnIndex();

            yield AssociationField::new('deliveries', '消息投递记录')
                ->hideOnForm()
                ->hideOnIndex();
        }

        yield DateTimeField::new('lastPingTime', '最后心跳时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->hideOnForm();

        yield DateTimeField::new('lastDeliverTime', '最后投递时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->hideOnForm();

        yield DateTimeField::new('lastActiveTime', '最后活跃时间')
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
        return $filters
            ->add(TextFilter::new('socketId', 'Socket ID'))
            ->add(TextFilter::new('sessionId', '会话ID'))
            ->add(TextFilter::new('clientId', '客户端ID'))
            ->add(TextFilter::new('namespace', '命名空间'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
            ->add(DateTimeFilter::new('updateTime', '更新时间'))
            ->add(DateTimeFilter::new('lastActiveTime', '最后活跃时间'));
    }

    public function configureActions(Actions $actions): Actions
    {
        $viewRooms = Action::new('viewRooms', '查看房间')
            ->linkToCrudAction('viewRooms')
            ->setCssClass('btn btn-info')
            ->setIcon('fa fa-door-open');

        $disconnectSocket = Action::new('disconnect', '断开连接')
            ->linkToCrudAction('disconnectSocket')
            ->setCssClass('btn btn-danger')
            ->setIcon('fa fa-plug');

        $refreshStatus = Action::new('refreshStatus', '刷新状态')
            ->linkToCrudAction('refreshStatus')
            ->setCssClass('btn btn-primary')
            ->setIcon('fa fa-sync');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $viewRooms)
            ->add(Crud::PAGE_DETAIL, $refreshStatus)
            ->add(Crud::PAGE_DETAIL, $disconnectSocket)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, Action::DELETE]);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->leftJoin('entity.rooms', 'rooms')
            ->leftJoin('entity.deliveries', 'deliveries')
            ->addSelect('rooms', 'deliveries');
    }

    /**
     * 自定义操作：查看房间
     */
    #[AdminAction(routePath: '{id}/view-rooms', routeName: 'view_rooms')]
    public function viewRooms(AdminContext $context): Response
    {
        $socket = $context->getEntity()->getInstance();
        $rooms = $socket->getRooms();

        // 重定向到房间列表，并添加过滤条件
        $url = $this->adminUrlGenerator
            ->setController(RoomCrudController::class)
            ->setAction(Action::INDEX)
            ->set('filters', [
                'sockets' => ['comparison' => '=', 'value' => $socket->getId()]
            ])
            ->generateUrl();

        return $this->redirect($url);
    }

    /**
     * 自定义操作：断开连接
     */
    #[AdminAction(routePath: '{id}/disconnect', routeName: 'disconnect_socket')]
    public function disconnectSocket(AdminContext $context): Response
    {
        $socket = $context->getEntity()->getInstance();
        $socket->setConnected(false);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('已断开 Socket 连接: %s', $socket->getSocketId()));

        return $this->redirect($context->getReferrer());
    }

    /**
     * 自定义操作：刷新状态
     */
    #[AdminAction(routePath: '{id}/refresh-status', routeName: 'refresh_status')]
    public function refreshStatus(AdminContext $context): Response
    {
        $socket = $context->getEntity()->getInstance();
        $socket->updateLastActiveTime();
        $socket->updatePingTime();
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('已刷新 Socket 状态: %s', $socket->getSocketId()));

        return $this->redirect($context->getReferrer());
    }
}
