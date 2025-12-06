<?php

use Beeralex\Core\Service\FileService;
use Beeralex\Core\Service\SortingService;
use Beeralex\Reviews\Contracts\CreatorContract;
use Beeralex\Reviews\Contracts\FileUploaderContract;
use Beeralex\Reviews\Enum\DIServiceKey;
use Beeralex\Reviews\Options;
use Beeralex\Reviews\Repository\ReviewsRepository;
use Beeralex\Reviews\Repository\SortingRepository;
use Beeralex\Reviews\Services\ReviewCreatorService;
use Beeralex\Reviews\Services\ReviewsService;
use Beeralex\Reviews\Services\UploadService;

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
			ReviewsService::class => [
				'constructor' => static function () {
					return new ReviewsService(
						service(CreatorContract::class)
					);
				},
			],
			CreatorContract::class => [
				'constructor' => static function () {
					return new ReviewCreatorService(
						service(FileUploaderContract::class),
						service(ReviewsRepository::class)
					);
				},
			],
			FileUploaderContract::class => [
				'constructor' => static function () {
					return new UploadService(service(FileService::class));
				},
			],
			ReviewsRepository::class => [
				'constructor' => static function () {
					return new ReviewsRepository('product_reviews');
				},
			],
			DIServiceKey::SORTING_REPOSITORY->value => [
				'className' => SortingRepository::class,
			],
			DIServiceKey::SORTING_SERVICE->value => [
				'constructor' => static function () {
					return new SortingService(
						service(DIServiceKey::SORTING_REPOSITORY->value)
					);
				},
			],
		],
		'readonly' => true,
	],
];
