# beeralex.reviews
## Модуль отзывов
Отзывы к товарам и к сайту

# Установка

добавьте в composer.json экстра опцию, чтобы композер поставил пакет в local/modules

```json
"extra": {
  "installer-paths": {
    "local/modules/{$name}/": ["type:bitrix-module"]
  }
}
```

```bash
composer require beeralex/beeralex.reviews
```

1. Установить пакеты из composer.json (установятся сами при установке через композер)
2. Для пакета php-ffmpeg/php-ffmpeg требуется чтобы на сервере был установлен пакет - ffmpeg. Пакет нужен для создания превью для загруженного видео
3. Установить модуль beeralex.core - https://git.beeralex-dev.ru/ITB-dev/beeralex.core
4. Для подключения компонента отзывов в catalog.element реализуется через некешируемую область (модули api.core и api.uncachedarea), хотя вероятнее всего шаблоны вы будете переделывать, но стоит учитывать что если компонент имеет script.js который компонент подключает, то подключение js и возможно css компонента в компоненте не работает.

ссылка на api.core - https://github.com/ASDAFF/api.core

ссылка на api.uncachedarea - https://github.com/ASDAFF/api.uncachedarea

## Класс Beeralex\Reviews\Options

Этот класс используется для получения настроек модуля и компонента, при подключении компонента в шаблоне можно получить объект класса с помощью метода:

```php
service(Options::class)
```

Объект класса вернется если он уже был создан, иначе null

## Параметры компонента

Через $arParams можно прокинуть следующие параметры в компонент:

- ```IBLOCK_ID``` - ИД инфоблока отзывов, по умолчанию будет взято значение из настроек модуля.
- ```PRODUCT_ID``` (тип int) - ид продукта для которого будут выбраны отзывы, то есть в catalog.element передаете id, если id не передается то будут выбраны все отзывы
- ```PAGINATION_LIMIT``` (тип int) - количество отзывов на странице
- ```SHOW_INFO_PRODUCT``` (тип bool) - дополнительная информация о товаре на общей странице отзывов (название товара и ссылка на него + картинка товара (PREVIEW_PICTURE либо DETAIL_PICTURE))
- ```PLATFORM``` (тип string) - C какой платформы доставать отзыв, по умолчанию пустая строка для выборки из всех платформ и 'site|2gis' для выборки с определенной.

## Пример подключения компонента

1. Подключение на общей странице отзывов:
```php
$APPLICATION->IncludeComponent(
	"beeralex:reviews", 
	"about", 
	[
		"PAGINATION_LIMIT" => "5",
		"SHOW_INFO_PRODUCT" => true,
		"COMPONENT_TEMPLATE" => "about",
		"CACHE_TYPE" => "A",
		"CACHE_TIME" => "86400",
		"PLATFORM" => "site|2gis|''"
	],
	false
);
```

2. Подключение на странице товара
```php
if (Loader::includeModule('api.uncachedarea') && Loader::includeModule('beeralex.reviews')) {
	CAPIUncachedArea::includeFile(
		"/include/components/reviews.php",
		["PRODUCT_ID" => $arResult['ID']]
	);
}
```
файл ```/include/components/reviews.php```:
```php
$APPLICATION->IncludeComponent(
	"beeralex:reviews", 
	".default", 
	[
		"PRODUCT_ID" => $arParams["PRODUCT_ID"],
		"PAGINATION_LIMIT" => "3",
		"CACHE_TYPE" => "A",
		"CACHE_TIME" => "86400"
	],
	false
);
```

## Импорт отзывов с других платформ
Сейчас реализован импорт с 2gis.
Для этого в настройках компонента заполните настройки:
1. Api ключ 2гис, если не нашли, то исползуйте с стрраницы 2гис, можно через сеть посмотреть ключ в запросе к api или взять этот если еще работает - 6e7e1929-4ea9-4a5d-8c05-d601860389bd
2. ID филиалов - каждый филиал вводить с новой строки

Для импорта без ошибок необходима кодировка базы - utf8bd4

проверить after_connect_d7: 
```php
$this->queryExecute("SET NAMES 'utf8mb4'");
$this->queryExecute('SET collation_connection = "utf8mb4_unicode_ci"');
```

После этого проверить агента - ``` \Beeralex\Reviews\Agents\Import::exec(); ```, при установке модуля он выключен.

Для импорта с других платформ наследуйтесь от класса ``` Beeralex\Reviews\Import\BaseImport ``` и реализуйте метод ``` process ``` в нем и должен быть реализован импорт. Для добавления отзывов в инфоблок используйте метод абстрактного класса ``` import ```, туда передается массив добовляемых отзывов, структуру можно посмотреть в классе ``` ImportFrom2Gis ```
Внешний ид должен быть числом.

После этого добавьте нового импортера в .settings.php модуля в секцию с регистрацией классов DI контейнере, на подобие с классом ``` ImportFrom2Gis ```

И наконец можете добавть название вашего класса в массив ``` $importPlatformsMap ``` агента ``` Beeralex\Reviews\Agents\Import ```. Этот массив должен хранить названия классов наслдников ``` BaseImport ``` и соответственно каждый класс должен быть добавлен в DI контейнер.

Так же не забудьте добавить код новой платформы в свойство ``` REVIEW_PLATFORM ``` инфоблока, xml_id соответствует значению перечесления класса ``` Beeralex\Reviews\Enum\Platforms ``` - туда так же нужно добавить новый кейс для вашей платформы.

## Vue компонент reviews-star

компонент reviews-star регистрируется глобально, то есть там где подключаются отзывы - вы можете использовать этот компонент в любом вашем vue приложении

На примере catalog.element на сайте без vue вы так же можете вывести рейтинг

1. В component_epilog компонента catalog.element добавьте следующую запись:
```php
if (Loader::includeModule('beeralex.reviews')) {

	$reviewsAvg = \Beeralex\Reviews\Helpers\EvalHelper::getAvg($arResult['ID']);
	?>
	<script>
		window.reviewsAvg = '<?= $reviewsAvg ?>';
	</script>
<?
}
```

2. 
в template.php в месте где вы хотите вывести рейтинг добавьте следующий код:

```php
<div id="app-review">
	<reviews-star :avg="window.reviewsAvg"></reviews-star>
</div>
<script>
	document.addEventListener('DOMContentLoaded', () => {
		new Vue({
			el: '#app-review'
		})
	})
</script>
```

## SignedParameters компонента

Компонент преобразует следующие параметры:
- ```PAGINATION_LIMIT```
- ```PRODUCT_ID```
- ```IBLOCK_ID```
- ```SHOW_INFO_PRODUCT```
- ```PLATFORM```

Чтобы корректно использовать ajax запросы в шаблоне компонента, передавайте эти параметры в теле запроса под ключом ```params```

```javascript
let formData = new URLSearchParams();
formData.append('params', JSON.stringify(this.params));
```

получить SignedParameters в шаблоне компонента: 

```php
$this->getComponent()->getSignedParameters()
```

## Контроллер для ajax запросов

В контроллере есть 4 маршрута для выполнения ajax запросов:

- ```add``` - Для добавления отзыва
- ```pagination``` - для получения отзывов по переданному массиву ```pagination``` (currentPage нужно изменять в js перед отправкой ajax запроса), так же передается массив ```sorting```
- ```sorting``` - для сортировки отзывов по переданному массиву ```sorting``` (field и type сортировки изменять в js перед отправкой ajax запроса), так же нужно передать массив ```pagination```
- ```get``` - для получения отзывов, можно передать ```product_id``` для получения отзывов только для этого товара

Так же для всех запросов нужно передавать SignedParameters под ключом ```params```

Все ссылки для выполнения запросов доступны в массиве ```actions```

в script.js шаблонов можно найти примеры выполнения ajax запросов