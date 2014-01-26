<?php

namespace Clue\Redis\Server;

use Clue\Redis\Protocol\Model\ErrorReply;
use ReflectionClass;
use ReflectionMethod;
use Exception;
use Clue\Redis\Protocol\Serializer\SerializerInterface;
use Clue\Redis\Protocol\Model\ModelInterface;

class Invoker
{
    private $business;
    private $commands = array();

    public function __construct($business, SerializerInterface $serializer)
    {
        $this->business = $business;
        $this->serializer = $serializer;

        $ref = new ReflectionClass($business);
        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            /* @var $method ReflectionMethod */
            $this->commands[$method->getName()] = $this->getNumberOfArguments($method);
        }
    }

    private function getNumberOfArguments(ReflectionMethod $method)
    {
        return $method->getNumberOfRequiredParameters();
    }

    public function invoke($command, array $args)
    {
        $command = strtolower($command);

        if (!isset($this->commands[$command])) {
            return $this->serializer->getErrorMessage('ERR Unknown or disabled command \'' . $command . '\'');
        }

        $n = count($args);
        if ($n < $this->commands[$command]) {
            return $this->serializer->getErrorMessage('ERR wrong number of arguments for \'' . $command . '\' command');
        }

        try {
            $ret = call_user_func_array(array($this->business, $command), $args);
        }
        catch (Exception $e) {
            return $this->serializer->getErrorMessage($e);
        }

        if ($ret instanceof ModelInterface) {
            return $ret->getMessageSerialized($this->serializer);
        }

        return $this->serializer->getReplyMessage($ret);
    }
}