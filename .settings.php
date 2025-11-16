<?php

use Beeralex\Reviews\Options;

return [
	'controllers' => [
		'value' => [
			'defaultNamespace' => '\\Beeralex\\Reviews\\Controllers',
		],
		'readonly' => true,
	],
	'services' => [
		'value' => [
			Options::class => [
				'className' => Options::class,
			],
			\Beeralex\Reviews\Contracts\CreatorContract::class => [
				'className' => Beeralex\Reviews\Services\ReviewCreatorService::class,
			],
			\Beeralex\Reviews\Contracts\FileUploaderContract::class => [
				'className' => Beeralex\Reviews\Services\UploadService::class,
			],
		],
		'readonly' => true,
	],
];
