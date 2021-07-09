<?php

namespace App\Logging;

use App\Services\Auth\Interfaces\IAuth;
use Illuminate\Http\Request;
use Monolog\Formatter\LineFormatter;

class CustomLog
{

    protected $request, $auth;

    public function __construct(Request $request = null, IAuth $auth)
    {
        $this->request = $request;
        $this->auth = $auth;
    }

    public function __invoke($logger, $config)
    {
        $request = $this->request;
        $userId = $this->auth->getBitrixId();
        foreach ($logger->getHandlers() as $handler){
            $handler->pushProcessor(function ($record) use ($request, $userId) {
                if (!is_array($record['context'])) {
                    $record['context'] = [$record['context']];
                }
                $function = !empty($record['context']['class']) ? class_basename($record['context']['class']) : '';
                $action = !empty($record['context']['function']) ? $record['context']['function'] : '';
                $record['context']['initMessage'] = $record['message'];
                $record['context'] = ['data' => $record['context']];
                $record['context']['ip'] = $request->ip();
                $sapi = php_sapi_name();
                $record['context']['sapi'] = $sapi;
                $record['context']['request_id'] = 'Unknown';
                $record['context']['user.id'] = $userId;
                $message = [
                    'app' => env('APP_NAME', 'travel'),
                    'module' => 'module',
                    'function' => $function,
                    'action' => $action,
                ];
                if($sapi == 'cli') {
                    $message['module'] = 'console';
                } else {
                    $record['context']['request_id'] = 'Unknown';
                    try {
                        $record['context']['request_id'] = $request->fingerprint();
                    } catch (\Throwable $e) {
                        //
                    }
                    $routeAction = $request->route()->getAction();
                    $controller = class_basename($routeAction['controller']);
                    $message['module'] = str_replace('@', '-', $controller);
                }

                if (empty($message['function'])) {
                    $message['function'] = 'systemerror';
                }

                if (empty($message['action'])) {
                    $message['action'] = 'unknown';
                }

                $record['message'] = strtolower(implode('.', $message));
                return $record;
            });

            $handler->setFormatter(new LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                'Y-m-d H:i:s.u',
                false,
                true
            ));
        }
    }
    public function format(array $record): string
    {
        if (isset($record["datetime"]) && ($record["datetime"] instanceof \DateTimeInterface)) {
            $record["timestamp"] = $record["datetime"]->format("Y-m-d\TH:i:s.uO");
            unset($record["datetime"]);
        }

        return parent::format($record);
    }

}
