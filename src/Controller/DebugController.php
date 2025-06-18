<?php

namespace SocketIoBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/socket-io/debug', name: 'socket_io_debug')]
class DebugController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('@SocketIo/debug.html.twig');
    }
}