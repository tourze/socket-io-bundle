<?php

namespace SocketIoBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DebugController extends AbstractController
{
    #[Route('/socket-io/debug', name: 'socket_io_debug')]
    public function debug(): Response
    {
        return $this->render('@SocketIo/debug.html.twig');
    }
}
