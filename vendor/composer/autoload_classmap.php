<?php

// autoload_classmap.php @generated by Composer

$vendorDir = dirname(__DIR__);
$baseDir = dirname($vendorDir);

return array(
    'ComfinoExternal\\Cache\\Adapter\\Common\\AbstractCachePool' => $vendorDir . '/cache/adapter-common/AbstractCachePool.php',
    'ComfinoExternal\\Cache\\Adapter\\Common\\CacheItem' => $vendorDir . '/cache/adapter-common/CacheItem.php',
    'ComfinoExternal\\Cache\\Adapter\\Common\\Exception\\CacheException' => $vendorDir . '/cache/adapter-common/Exception/CacheException.php',
    'ComfinoExternal\\Cache\\Adapter\\Common\\Exception\\CachePoolException' => $vendorDir . '/cache/adapter-common/Exception/CachePoolException.php',
    'ComfinoExternal\\Cache\\Adapter\\Common\\Exception\\InvalidArgumentException' => $vendorDir . '/cache/adapter-common/Exception/InvalidArgumentException.php',
    'ComfinoExternal\\Cache\\Adapter\\Common\\HasExpirationTimestampInterface' => $vendorDir . '/cache/adapter-common/HasExpirationTimestampInterface.php',
    'ComfinoExternal\\Cache\\Adapter\\Common\\JsonBinaryArmoring' => $vendorDir . '/cache/adapter-common/JsonBinaryArmoring.php',
    'ComfinoExternal\\Cache\\Adapter\\Common\\PhpCacheItem' => $vendorDir . '/cache/adapter-common/PhpCacheItem.php',
    'ComfinoExternal\\Cache\\Adapter\\Common\\PhpCachePool' => $vendorDir . '/cache/adapter-common/PhpCachePool.php',
    'ComfinoExternal\\Cache\\Adapter\\Common\\TagSupportWithArray' => $vendorDir . '/cache/adapter-common/TagSupportWithArray.php',
    'ComfinoExternal\\Cache\\Adapter\\Filesystem\\FilesystemCachePool' => $vendorDir . '/cache/filesystem-adapter/FilesystemCachePool.php',
    'ComfinoExternal\\Cache\\Adapter\\PHPArray\\ArrayCachePool' => $vendorDir . '/cache/array-adapter/ArrayCachePool.php',
    'ComfinoExternal\\Cache\\Hierarchy\\HierarchicalCachePoolTrait' => $vendorDir . '/cache/hierarchical-cache/HierarchicalCachePoolTrait.php',
    'ComfinoExternal\\Cache\\Hierarchy\\HierarchicalPoolInterface' => $vendorDir . '/cache/hierarchical-cache/HierarchicalPoolInterface.php',
    'ComfinoExternal\\Cache\\TagInterop\\TaggableCacheItemInterface' => $vendorDir . '/cache/tag-interop/TaggableCacheItemInterface.php',
    'ComfinoExternal\\Cache\\TagInterop\\TaggableCacheItemPoolInterface' => $vendorDir . '/cache/tag-interop/TaggableCacheItemPoolInterface.php',
    'ComfinoExternal\\Fig\\Http\\Message\\RequestMethodInterface' => $vendorDir . '/fig/http-message-util/src/RequestMethodInterface.php',
    'ComfinoExternal\\Fig\\Http\\Message\\StatusCodeInterface' => $vendorDir . '/fig/http-message-util/src/StatusCodeInterface.php',
    'ComfinoExternal\\League\\Flysystem\\AdapterInterface' => $vendorDir . '/league/flysystem/src/AdapterInterface.php',
    'ComfinoExternal\\League\\Flysystem\\Adapter\\AbstractAdapter' => $vendorDir . '/league/flysystem/src/Adapter/AbstractAdapter.php',
    'ComfinoExternal\\League\\Flysystem\\Adapter\\AbstractFtpAdapter' => $vendorDir . '/league/flysystem/src/Adapter/AbstractFtpAdapter.php',
    'ComfinoExternal\\League\\Flysystem\\Adapter\\CanOverwriteFiles' => $vendorDir . '/league/flysystem/src/Adapter/CanOverwriteFiles.php',
    'ComfinoExternal\\League\\Flysystem\\Adapter\\Ftp' => $vendorDir . '/league/flysystem/src/Adapter/Ftp.php',
    'ComfinoExternal\\League\\Flysystem\\Adapter\\Ftpd' => $vendorDir . '/league/flysystem/src/Adapter/Ftpd.php',
    'ComfinoExternal\\League\\Flysystem\\Adapter\\Local' => $vendorDir . '/league/flysystem/src/Adapter/Local.php',
    'ComfinoExternal\\League\\Flysystem\\Adapter\\NullAdapter' => $vendorDir . '/league/flysystem/src/Adapter/NullAdapter.php',
    'ComfinoExternal\\League\\Flysystem\\Adapter\\Polyfill\\NotSupportingVisibilityTrait' => $vendorDir . '/league/flysystem/src/Adapter/Polyfill/NotSupportingVisibilityTrait.php',
    'ComfinoExternal\\League\\Flysystem\\Adapter\\Polyfill\\StreamedCopyTrait' => $vendorDir . '/league/flysystem/src/Adapter/Polyfill/StreamedCopyTrait.php',
    'ComfinoExternal\\League\\Flysystem\\Adapter\\Polyfill\\StreamedReadingTrait' => $vendorDir . '/league/flysystem/src/Adapter/Polyfill/StreamedReadingTrait.php',
    'ComfinoExternal\\League\\Flysystem\\Adapter\\Polyfill\\StreamedTrait' => $vendorDir . '/league/flysystem/src/Adapter/Polyfill/StreamedTrait.php',
    'ComfinoExternal\\League\\Flysystem\\Adapter\\Polyfill\\StreamedWritingTrait' => $vendorDir . '/league/flysystem/src/Adapter/Polyfill/StreamedWritingTrait.php',
    'ComfinoExternal\\League\\Flysystem\\Adapter\\SynologyFtp' => $vendorDir . '/league/flysystem/src/Adapter/SynologyFtp.php',
    'ComfinoExternal\\League\\Flysystem\\Config' => $vendorDir . '/league/flysystem/src/Config.php',
    'ComfinoExternal\\League\\Flysystem\\ConfigAwareTrait' => $vendorDir . '/league/flysystem/src/ConfigAwareTrait.php',
    'ComfinoExternal\\League\\Flysystem\\ConnectionErrorException' => $vendorDir . '/league/flysystem/src/ConnectionErrorException.php',
    'ComfinoExternal\\League\\Flysystem\\ConnectionRuntimeException' => $vendorDir . '/league/flysystem/src/ConnectionRuntimeException.php',
    'ComfinoExternal\\League\\Flysystem\\CorruptedPathDetected' => $vendorDir . '/league/flysystem/src/CorruptedPathDetected.php',
    'ComfinoExternal\\League\\Flysystem\\Directory' => $vendorDir . '/league/flysystem/src/Directory.php',
    'ComfinoExternal\\League\\Flysystem\\Exception' => $vendorDir . '/league/flysystem/src/Exception.php',
    'ComfinoExternal\\League\\Flysystem\\File' => $vendorDir . '/league/flysystem/src/File.php',
    'ComfinoExternal\\League\\Flysystem\\FileExistsException' => $vendorDir . '/league/flysystem/src/FileExistsException.php',
    'ComfinoExternal\\League\\Flysystem\\FileNotFoundException' => $vendorDir . '/league/flysystem/src/FileNotFoundException.php',
    'ComfinoExternal\\League\\Flysystem\\Filesystem' => $vendorDir . '/league/flysystem/src/Filesystem.php',
    'ComfinoExternal\\League\\Flysystem\\FilesystemException' => $vendorDir . '/league/flysystem/src/FilesystemException.php',
    'ComfinoExternal\\League\\Flysystem\\FilesystemInterface' => $vendorDir . '/league/flysystem/src/FilesystemInterface.php',
    'ComfinoExternal\\League\\Flysystem\\FilesystemNotFoundException' => $vendorDir . '/league/flysystem/src/FilesystemNotFoundException.php',
    'ComfinoExternal\\League\\Flysystem\\Handler' => $vendorDir . '/league/flysystem/src/Handler.php',
    'ComfinoExternal\\League\\Flysystem\\InvalidRootException' => $vendorDir . '/league/flysystem/src/InvalidRootException.php',
    'ComfinoExternal\\League\\Flysystem\\MountManager' => $vendorDir . '/league/flysystem/src/MountManager.php',
    'ComfinoExternal\\League\\Flysystem\\NotSupportedException' => $vendorDir . '/league/flysystem/src/NotSupportedException.php',
    'ComfinoExternal\\League\\Flysystem\\PluginInterface' => $vendorDir . '/league/flysystem/src/PluginInterface.php',
    'ComfinoExternal\\League\\Flysystem\\Plugin\\AbstractPlugin' => $vendorDir . '/league/flysystem/src/Plugin/AbstractPlugin.php',
    'ComfinoExternal\\League\\Flysystem\\Plugin\\EmptyDir' => $vendorDir . '/league/flysystem/src/Plugin/EmptyDir.php',
    'ComfinoExternal\\League\\Flysystem\\Plugin\\ForcedCopy' => $vendorDir . '/league/flysystem/src/Plugin/ForcedCopy.php',
    'ComfinoExternal\\League\\Flysystem\\Plugin\\ForcedRename' => $vendorDir . '/league/flysystem/src/Plugin/ForcedRename.php',
    'ComfinoExternal\\League\\Flysystem\\Plugin\\GetWithMetadata' => $vendorDir . '/league/flysystem/src/Plugin/GetWithMetadata.php',
    'ComfinoExternal\\League\\Flysystem\\Plugin\\ListFiles' => $vendorDir . '/league/flysystem/src/Plugin/ListFiles.php',
    'ComfinoExternal\\League\\Flysystem\\Plugin\\ListPaths' => $vendorDir . '/league/flysystem/src/Plugin/ListPaths.php',
    'ComfinoExternal\\League\\Flysystem\\Plugin\\ListWith' => $vendorDir . '/league/flysystem/src/Plugin/ListWith.php',
    'ComfinoExternal\\League\\Flysystem\\Plugin\\PluggableTrait' => $vendorDir . '/league/flysystem/src/Plugin/PluggableTrait.php',
    'ComfinoExternal\\League\\Flysystem\\Plugin\\PluginNotFoundException' => $vendorDir . '/league/flysystem/src/Plugin/PluginNotFoundException.php',
    'ComfinoExternal\\League\\Flysystem\\ReadInterface' => $vendorDir . '/league/flysystem/src/ReadInterface.php',
    'ComfinoExternal\\League\\Flysystem\\RootViolationException' => $vendorDir . '/league/flysystem/src/RootViolationException.php',
    'ComfinoExternal\\League\\Flysystem\\SafeStorage' => $vendorDir . '/league/flysystem/src/SafeStorage.php',
    'ComfinoExternal\\League\\Flysystem\\UnreadableFileException' => $vendorDir . '/league/flysystem/src/UnreadableFileException.php',
    'ComfinoExternal\\League\\Flysystem\\Util' => $vendorDir . '/league/flysystem/src/Util.php',
    'ComfinoExternal\\League\\Flysystem\\Util\\ContentListingFormatter' => $vendorDir . '/league/flysystem/src/Util/ContentListingFormatter.php',
    'ComfinoExternal\\League\\Flysystem\\Util\\MimeType' => $vendorDir . '/league/flysystem/src/Util/MimeType.php',
    'ComfinoExternal\\League\\Flysystem\\Util\\StreamHasher' => $vendorDir . '/league/flysystem/src/Util/StreamHasher.php',
    'ComfinoExternal\\League\\MimeTypeDetection\\EmptyExtensionToMimeTypeMap' => $vendorDir . '/league/mime-type-detection/src/EmptyExtensionToMimeTypeMap.php',
    'ComfinoExternal\\League\\MimeTypeDetection\\ExtensionLookup' => $vendorDir . '/league/mime-type-detection/src/ExtensionLookup.php',
    'ComfinoExternal\\League\\MimeTypeDetection\\ExtensionMimeTypeDetector' => $vendorDir . '/league/mime-type-detection/src/ExtensionMimeTypeDetector.php',
    'ComfinoExternal\\League\\MimeTypeDetection\\ExtensionToMimeTypeMap' => $vendorDir . '/league/mime-type-detection/src/ExtensionToMimeTypeMap.php',
    'ComfinoExternal\\League\\MimeTypeDetection\\FinfoMimeTypeDetector' => $vendorDir . '/league/mime-type-detection/src/FinfoMimeTypeDetector.php',
    'ComfinoExternal\\League\\MimeTypeDetection\\GeneratedExtensionToMimeTypeMap' => $vendorDir . '/league/mime-type-detection/src/GeneratedExtensionToMimeTypeMap.php',
    'ComfinoExternal\\League\\MimeTypeDetection\\MimeTypeDetector' => $vendorDir . '/league/mime-type-detection/src/MimeTypeDetector.php',
    'ComfinoExternal\\League\\MimeTypeDetection\\OverridingExtensionToMimeTypeMap' => $vendorDir . '/league/mime-type-detection/src/OverridingExtensionToMimeTypeMap.php',
    'ComfinoExternal\\Psr\\Cache\\CacheException' => $vendorDir . '/psr/cache/src/CacheException.php',
    'ComfinoExternal\\Psr\\Cache\\CacheItemInterface' => $vendorDir . '/psr/cache/src/CacheItemInterface.php',
    'ComfinoExternal\\Psr\\Cache\\CacheItemPoolInterface' => $vendorDir . '/psr/cache/src/CacheItemPoolInterface.php',
    'ComfinoExternal\\Psr\\Cache\\InvalidArgumentException' => $vendorDir . '/psr/cache/src/InvalidArgumentException.php',
    'ComfinoExternal\\Psr\\Http\\Client\\ClientExceptionInterface' => $vendorDir . '/psr/http-client/src/ClientExceptionInterface.php',
    'ComfinoExternal\\Psr\\Http\\Client\\ClientInterface' => $vendorDir . '/psr/http-client/src/ClientInterface.php',
    'ComfinoExternal\\Psr\\Http\\Client\\NetworkExceptionInterface' => $vendorDir . '/psr/http-client/src/NetworkExceptionInterface.php',
    'ComfinoExternal\\Psr\\Http\\Client\\RequestExceptionInterface' => $vendorDir . '/psr/http-client/src/RequestExceptionInterface.php',
    'ComfinoExternal\\Psr\\Http\\Message\\MessageInterface' => $vendorDir . '/psr/http-message/src/MessageInterface.php',
    'ComfinoExternal\\Psr\\Http\\Message\\RequestFactoryInterface' => $vendorDir . '/psr/http-factory/src/RequestFactoryInterface.php',
    'ComfinoExternal\\Psr\\Http\\Message\\RequestInterface' => $vendorDir . '/psr/http-message/src/RequestInterface.php',
    'ComfinoExternal\\Psr\\Http\\Message\\ResponseFactoryInterface' => $vendorDir . '/psr/http-factory/src/ResponseFactoryInterface.php',
    'ComfinoExternal\\Psr\\Http\\Message\\ResponseInterface' => $vendorDir . '/psr/http-message/src/ResponseInterface.php',
    'ComfinoExternal\\Psr\\Http\\Message\\ServerRequestFactoryInterface' => $vendorDir . '/psr/http-factory/src/ServerRequestFactoryInterface.php',
    'ComfinoExternal\\Psr\\Http\\Message\\ServerRequestInterface' => $vendorDir . '/psr/http-message/src/ServerRequestInterface.php',
    'ComfinoExternal\\Psr\\Http\\Message\\StreamFactoryInterface' => $vendorDir . '/psr/http-factory/src/StreamFactoryInterface.php',
    'ComfinoExternal\\Psr\\Http\\Message\\StreamInterface' => $vendorDir . '/psr/http-message/src/StreamInterface.php',
    'ComfinoExternal\\Psr\\Http\\Message\\UploadedFileFactoryInterface' => $vendorDir . '/psr/http-factory/src/UploadedFileFactoryInterface.php',
    'ComfinoExternal\\Psr\\Http\\Message\\UploadedFileInterface' => $vendorDir . '/psr/http-message/src/UploadedFileInterface.php',
    'ComfinoExternal\\Psr\\Http\\Message\\UriFactoryInterface' => $vendorDir . '/psr/http-factory/src/UriFactoryInterface.php',
    'ComfinoExternal\\Psr\\Http\\Message\\UriInterface' => $vendorDir . '/psr/http-message/src/UriInterface.php',
    'ComfinoExternal\\Psr\\Log\\AbstractLogger' => $vendorDir . '/psr/log/Psr/Log/AbstractLogger.php',
    'ComfinoExternal\\Psr\\Log\\InvalidArgumentException' => $vendorDir . '/psr/log/Psr/Log/InvalidArgumentException.php',
    'ComfinoExternal\\Psr\\Log\\LogLevel' => $vendorDir . '/psr/log/Psr/Log/LogLevel.php',
    'ComfinoExternal\\Psr\\Log\\LoggerAwareInterface' => $vendorDir . '/psr/log/Psr/Log/LoggerAwareInterface.php',
    'ComfinoExternal\\Psr\\Log\\LoggerAwareTrait' => $vendorDir . '/psr/log/Psr/Log/LoggerAwareTrait.php',
    'ComfinoExternal\\Psr\\Log\\LoggerInterface' => $vendorDir . '/psr/log/Psr/Log/LoggerInterface.php',
    'ComfinoExternal\\Psr\\Log\\LoggerTrait' => $vendorDir . '/psr/log/Psr/Log/LoggerTrait.php',
    'ComfinoExternal\\Psr\\Log\\NullLogger' => $vendorDir . '/psr/log/Psr/Log/NullLogger.php',
    'ComfinoExternal\\Psr\\Log\\Test\\DummyTest' => $vendorDir . '/psr/log/Psr/Log/Test/DummyTest.php',
    'ComfinoExternal\\Psr\\Log\\Test\\LoggerInterfaceTest' => $vendorDir . '/psr/log/Psr/Log/Test/LoggerInterfaceTest.php',
    'ComfinoExternal\\Psr\\Log\\Test\\TestLogger' => $vendorDir . '/psr/log/Psr/Log/Test/TestLogger.php',
    'ComfinoExternal\\Psr\\SimpleCache\\CacheException' => $vendorDir . '/psr/simple-cache/src/CacheException.php',
    'ComfinoExternal\\Psr\\SimpleCache\\CacheInterface' => $vendorDir . '/psr/simple-cache/src/CacheInterface.php',
    'ComfinoExternal\\Psr\\SimpleCache\\InvalidArgumentException' => $vendorDir . '/psr/simple-cache/src/InvalidArgumentException.php',
    'ComfinoExternal\\Sunrise\\Http\\Client\\Curl\\Client' => $vendorDir . '/sunrise/http-client-curl/src/Client.php',
    'ComfinoExternal\\Sunrise\\Http\\Client\\Curl\\Exception\\ClientException' => $vendorDir . '/sunrise/http-client-curl/src/Exception/ClientException.php',
    'ComfinoExternal\\Sunrise\\Http\\Client\\Curl\\Exception\\NetworkException' => $vendorDir . '/sunrise/http-client-curl/src/Exception/NetworkException.php',
    'ComfinoExternal\\Sunrise\\Http\\Client\\Curl\\Exception\\RequestException' => $vendorDir . '/sunrise/http-client-curl/src/Exception/RequestException.php',
    'ComfinoExternal\\Sunrise\\Http\\Factory\\RequestFactory' => $vendorDir . '/sunrise/http-factory/src/RequestFactory.php',
    'ComfinoExternal\\Sunrise\\Http\\Factory\\ResponseFactory' => $vendorDir . '/sunrise/http-factory/src/ResponseFactory.php',
    'ComfinoExternal\\Sunrise\\Http\\Factory\\ServerRequestFactory' => $vendorDir . '/sunrise/http-factory/src/ServerRequestFactory.php',
    'ComfinoExternal\\Sunrise\\Http\\Factory\\StreamFactory' => $vendorDir . '/sunrise/http-factory/src/StreamFactory.php',
    'ComfinoExternal\\Sunrise\\Http\\Factory\\UploadedFileFactory' => $vendorDir . '/sunrise/http-factory/src/UploadedFileFactory.php',
    'ComfinoExternal\\Sunrise\\Http\\Factory\\UriFactory' => $vendorDir . '/sunrise/http-factory/src/UriFactory.php',
    'ComfinoExternal\\Sunrise\\Http\\Header\\HeaderInterface' => $vendorDir . '/sunrise/http-header/src/HeaderInterface.php',
    'ComfinoExternal\\Sunrise\\Http\\Message\\Message' => $vendorDir . '/sunrise/http-message/src/Message.php',
    'ComfinoExternal\\Sunrise\\Http\\Message\\Request' => $vendorDir . '/sunrise/http-message/src/Request.php',
    'ComfinoExternal\\Sunrise\\Http\\Message\\RequestFactory' => $vendorDir . '/sunrise/http-message/src/RequestFactory.php',
    'ComfinoExternal\\Sunrise\\Http\\Message\\Response' => $vendorDir . '/sunrise/http-message/src/Response.php',
    'ComfinoExternal\\Sunrise\\Http\\Message\\ResponseFactory' => $vendorDir . '/sunrise/http-message/src/ResponseFactory.php',
    'ComfinoExternal\\Sunrise\\Http\\ServerRequest\\ServerRequest' => $vendorDir . '/sunrise/http-server-request/src/ServerRequest.php',
    'ComfinoExternal\\Sunrise\\Http\\ServerRequest\\ServerRequestFactory' => $vendorDir . '/sunrise/http-server-request/src/ServerRequestFactory.php',
    'ComfinoExternal\\Sunrise\\Http\\ServerRequest\\UploadedFile' => $vendorDir . '/sunrise/http-server-request/src/UploadedFile.php',
    'ComfinoExternal\\Sunrise\\Http\\ServerRequest\\UploadedFileFactory' => $vendorDir . '/sunrise/http-server-request/src/UploadedFileFactory.php',
    'ComfinoExternal\\Sunrise\\Stream\\Exception\\UnopenableStreamException' => $vendorDir . '/sunrise/stream/src/Exception/UnopenableStreamException.php',
    'ComfinoExternal\\Sunrise\\Stream\\Exception\\UnreadableStreamException' => $vendorDir . '/sunrise/stream/src/Exception/UnreadableStreamException.php',
    'ComfinoExternal\\Sunrise\\Stream\\Exception\\UnseekableStreamException' => $vendorDir . '/sunrise/stream/src/Exception/UnseekableStreamException.php',
    'ComfinoExternal\\Sunrise\\Stream\\Exception\\UntellableStreamException' => $vendorDir . '/sunrise/stream/src/Exception/UntellableStreamException.php',
    'ComfinoExternal\\Sunrise\\Stream\\Exception\\UnwritableStreamException' => $vendorDir . '/sunrise/stream/src/Exception/UnwritableStreamException.php',
    'ComfinoExternal\\Sunrise\\Stream\\Stream' => $vendorDir . '/sunrise/stream/src/Stream.php',
    'ComfinoExternal\\Sunrise\\Stream\\StreamFactory' => $vendorDir . '/sunrise/stream/src/StreamFactory.php',
    'ComfinoExternal\\Sunrise\\Uri\\Component\\ComponentInterface' => $vendorDir . '/sunrise/uri/src/Component/ComponentInterface.php',
    'ComfinoExternal\\Sunrise\\Uri\\Component\\Fragment' => $vendorDir . '/sunrise/uri/src/Component/Fragment.php',
    'ComfinoExternal\\Sunrise\\Uri\\Component\\Host' => $vendorDir . '/sunrise/uri/src/Component/Host.php',
    'ComfinoExternal\\Sunrise\\Uri\\Component\\Pass' => $vendorDir . '/sunrise/uri/src/Component/Pass.php',
    'ComfinoExternal\\Sunrise\\Uri\\Component\\Path' => $vendorDir . '/sunrise/uri/src/Component/Path.php',
    'ComfinoExternal\\Sunrise\\Uri\\Component\\Port' => $vendorDir . '/sunrise/uri/src/Component/Port.php',
    'ComfinoExternal\\Sunrise\\Uri\\Component\\Query' => $vendorDir . '/sunrise/uri/src/Component/Query.php',
    'ComfinoExternal\\Sunrise\\Uri\\Component\\Scheme' => $vendorDir . '/sunrise/uri/src/Component/Scheme.php',
    'ComfinoExternal\\Sunrise\\Uri\\Component\\User' => $vendorDir . '/sunrise/uri/src/Component/User.php',
    'ComfinoExternal\\Sunrise\\Uri\\Component\\UserInfo' => $vendorDir . '/sunrise/uri/src/Component/UserInfo.php',
    'ComfinoExternal\\Sunrise\\Uri\\Exception\\InvalidUriComponentException' => $vendorDir . '/sunrise/uri/src/Exception/InvalidUriComponentException.php',
    'ComfinoExternal\\Sunrise\\Uri\\Exception\\InvalidUriException' => $vendorDir . '/sunrise/uri/src/Exception/InvalidUriException.php',
    'ComfinoExternal\\Sunrise\\Uri\\Uri' => $vendorDir . '/sunrise/uri/src/Uri.php',
    'ComfinoExternal\\Sunrise\\Uri\\UriFactory' => $vendorDir . '/sunrise/uri/src/UriFactory.php',
    'ComfinoExternal\\Sunrise\\Uri\\UriParser' => $vendorDir . '/sunrise/uri/src/UriParser.php',
    'Comfino\\Api\\ApiClient' => $baseDir . '/src/Api/ApiClient.php',
    'Comfino\\Api\\ApiService' => $baseDir . '/src/Api/ApiService.php',
    'Comfino\\Api\\Client' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Client.php',
    'Comfino\\Api\\Dto\\Order\\Cart' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Dto/Order/Cart.php',
    'Comfino\\Api\\Dto\\Order\\Cart\\CartItem' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Dto/Order/Cart/CartItem.php',
    'Comfino\\Api\\Dto\\Order\\Customer' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Dto/Order/Customer.php',
    'Comfino\\Api\\Dto\\Order\\Customer\\Address' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Dto/Order/Customer/Address.php',
    'Comfino\\Api\\Dto\\Order\\LoanParameters' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Dto/Order/LoanParameters.php',
    'Comfino\\Api\\Dto\\Payment\\FinancialProduct' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Dto/Payment/FinancialProduct.php',
    'Comfino\\Api\\Dto\\Payment\\LoanParameters' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Dto/Payment/LoanParameters.php',
    'Comfino\\Api\\Dto\\Payment\\LoanQueryCriteria' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Dto/Payment/LoanQueryCriteria.php',
    'Comfino\\Api\\Dto\\Payment\\LoanTypeEnum' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Dto/Payment/LoanTypeEnum.php',
    'Comfino\\Api\\Exception\\AccessDenied' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Exception/AccessDenied.php',
    'Comfino\\Api\\Exception\\AuthorizationError' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Exception/AuthorizationError.php',
    'Comfino\\Api\\Exception\\RequestValidationError' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Exception/RequestValidationError.php',
    'Comfino\\Api\\Exception\\ResponseValidationError' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Exception/ResponseValidationError.php',
    'Comfino\\Api\\Exception\\ServiceUnavailable' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Exception/ServiceUnavailable.php',
    'Comfino\\Api\\Request' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Request.php',
    'Comfino\\Api\\Request\\CancelOrder' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Request/CancelOrder.php',
    'Comfino\\Api\\Request\\CreateOrder' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Request/CreateOrder.php',
    'Comfino\\Api\\Request\\GetFinancialProducts' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Request/GetFinancialProducts.php',
    'Comfino\\Api\\Request\\GetOrder' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Request/GetOrder.php',
    'Comfino\\Api\\Request\\GetPaywall' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Request/GetPaywall.php',
    'Comfino\\Api\\Request\\GetPaywallFragments' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Request/GetPaywallFragments.php',
    'Comfino\\Api\\Request\\GetProductTypes' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Request/GetProductTypes.php',
    'Comfino\\Api\\Request\\GetWidgetKey' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Request/GetWidgetKey.php',
    'Comfino\\Api\\Request\\GetWidgetTypes' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Request/GetWidgetTypes.php',
    'Comfino\\Api\\Request\\IsShopAccountActive' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Request/IsShopAccountActive.php',
    'Comfino\\Api\\Response' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Response.php',
    'Comfino\\Api\\Response\\Base' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Response/Base.php',
    'Comfino\\Api\\Response\\CreateOrder' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Response/CreateOrder.php',
    'Comfino\\Api\\Response\\GetFinancialProducts' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Response/GetFinancialProducts.php',
    'Comfino\\Api\\Response\\GetOrder' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Response/GetOrder.php',
    'Comfino\\Api\\Response\\GetPaywall' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Response/GetPaywall.php',
    'Comfino\\Api\\Response\\GetPaywallFragments' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Response/GetPaywallFragments.php',
    'Comfino\\Api\\Response\\GetProductTypes' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Response/GetProductTypes.php',
    'Comfino\\Api\\Response\\GetWidgetKey' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Response/GetWidgetKey.php',
    'Comfino\\Api\\Response\\GetWidgetTypes' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Response/GetWidgetTypes.php',
    'Comfino\\Api\\Response\\IsShopAccountActive' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Response/IsShopAccountActive.php',
    'Comfino\\Api\\SerializerInterface' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/SerializerInterface.php',
    'Comfino\\Api\\Serializer\\Json' => $vendorDir . '/comfino/shop-plugins-shared/src/Api/Serializer/Json.php',
    'Comfino\\CacheManager' => $baseDir . '/src/CacheManager.php',
    'Comfino\\CategoryTree\\BuildStrategy' => $baseDir . '/src/CategoryTree/BuildStrategy.php',
    'Comfino\\Common\\Backend\\Cache\\ItemTypeEnum' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Backend/Cache/ItemTypeEnum.php',
    'Comfino\\Common\\Backend\\ConfigurationManager' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Backend/ConfigurationManager.php',
    'Comfino\\Common\\Backend\\Configuration\\StorageAdapterInterface' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Backend/Configuration/StorageAdapterInterface.php',
    'Comfino\\Common\\Backend\\ErrorLogger' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Backend/ErrorLogger.php',
    'Comfino\\Common\\Backend\\Factory\\ApiClientFactory' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Backend/Factory/ApiClientFactory.php',
    'Comfino\\Common\\Backend\\Factory\\ApiServiceFactory' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Backend/Factory/ApiServiceFactory.php',
    'Comfino\\Common\\Backend\\Factory\\OrderFactory' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Backend/Factory/OrderFactory.php',
    'Comfino\\Common\\Backend\\Logger\\StorageAdapterInterface' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Backend/Logger/StorageAdapterInterface.php',
    'Comfino\\Common\\Backend\\Payment\\ProductTypeFilterInterface' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Backend/Payment/ProductTypeFilterInterface.php',
    'Comfino\\Common\\Backend\\Payment\\ProductTypeFilterManager' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Backend/Payment/ProductTypeFilterManager.php',
    'Comfino\\Common\\Backend\\Payment\\ProductTypeFilter\\FilterByCartValueLowerLimit' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Backend/Payment/ProductTypeFilter/FilterByCartValueLowerLimit.php',
    'Comfino\\Common\\Backend\\Payment\\ProductTypeFilter\\FilterByCartValueUpperLimit' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Backend/Payment/ProductTypeFilter/FilterByCartValueUpperLimit.php',
    'Comfino\\Common\\Backend\\Payment\\ProductTypeFilter\\FilterByExcludedCategory' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Backend/Payment/ProductTypeFilter/FilterByExcludedCategory.php',
    'Comfino\\Common\\Backend\\Payment\\ProductTypeFilter\\FilterByProductType' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Backend/Payment/ProductTypeFilter/FilterByProductType.php',
    'Comfino\\Common\\Backend\\Payment\\ProductTypeTools' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Backend/Payment/ProductTypeTools.php',
    'Comfino\\Common\\Backend\\RestEndpoint' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Backend/RestEndpoint.php',
    'Comfino\\Common\\Backend\\RestEndpointInterface' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Backend/RestEndpointInterface.php',
    'Comfino\\Common\\Backend\\RestEndpointManager' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Backend/RestEndpointManager.php',
    'Comfino\\Common\\Backend\\RestEndpoint\\CacheInvalidate' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Backend/RestEndpoint/CacheInvalidate.php',
    'Comfino\\Common\\Backend\\RestEndpoint\\Configuration' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Backend/RestEndpoint/Configuration.php',
    'Comfino\\Common\\Backend\\RestEndpoint\\StatusNotification' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Backend/RestEndpoint/StatusNotification.php',
    'Comfino\\Common\\Exception\\InvalidEndpoint' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Exception/InvalidEndpoint.php',
    'Comfino\\Common\\Exception\\InvalidRequest' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Exception/InvalidRequest.php',
    'Comfino\\Common\\Frontend\\FrontendRenderer' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Frontend/FrontendRenderer.php',
    'Comfino\\Common\\Frontend\\PaywallIframeRenderer' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Frontend/PaywallIframeRenderer.php',
    'Comfino\\Common\\Frontend\\PaywallRenderer' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Frontend/PaywallRenderer.php',
    'Comfino\\Common\\Frontend\\TemplateRenderer\\RendererStrategyInterface' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Frontend/TemplateRenderer/RendererStrategyInterface.php',
    'Comfino\\Common\\Frontend\\WidgetIframeRenderer' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Frontend/WidgetIframeRenderer.php',
    'Comfino\\Common\\Shop\\Cart' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Shop/Cart.php',
    'Comfino\\Common\\Shop\\OrderStatusAdapterInterface' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Shop/OrderStatusAdapterInterface.php',
    'Comfino\\Common\\Shop\\Order\\StatusManager' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Shop/Order/StatusManager.php',
    'Comfino\\Common\\Shop\\Product\\Category' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Shop/Product/Category.php',
    'Comfino\\Common\\Shop\\Product\\CategoryFilter' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Shop/Product/CategoryFilter.php',
    'Comfino\\Common\\Shop\\Product\\CategoryManager' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Shop/Product/CategoryManager.php',
    'Comfino\\Common\\Shop\\Product\\CategoryTree' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Shop/Product/CategoryTree.php',
    'Comfino\\Common\\Shop\\Product\\CategoryTree\\BuildStrategyInterface' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Shop/Product/CategoryTree/BuildStrategyInterface.php',
    'Comfino\\Common\\Shop\\Product\\CategoryTree\\Descriptor' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Shop/Product/CategoryTree/Descriptor.php',
    'Comfino\\Common\\Shop\\Product\\CategoryTree\\Node' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Shop/Product/CategoryTree/Node.php',
    'Comfino\\Common\\Shop\\Product\\CategoryTree\\NodeIterator' => $vendorDir . '/comfino/shop-plugins-shared/src/Common/Shop/Product/CategoryTree/NodeIterator.php',
    'Comfino\\Configuration\\ConfigManager' => $baseDir . '/src/Configuration/ConfigManager.php',
    'Comfino\\Configuration\\SettingsManager' => $baseDir . '/src/Configuration/SettingsManager.php',
    'Comfino\\Configuration\\StorageAdapter' => $baseDir . '/src/Configuration/StorageAdapter.php',
    'Comfino\\Enum' => $vendorDir . '/comfino/shop-plugins-shared/src/Enum.php',
    'Comfino\\ErrorLogger' => $baseDir . '/src/ErrorLogger.php',
    'Comfino\\ErrorLogger\\StorageAdapter' => $baseDir . '/src/ErrorLogger/StorageAdapter.php',
    'Comfino\\Extended\\Api\\Client' => $vendorDir . '/comfino/shop-plugins-shared/src/Extended/Api/Client.php',
    'Comfino\\Extended\\Api\\Dto\\Plugin\\ShopPluginError' => $vendorDir . '/comfino/shop-plugins-shared/src/Extended/Api/Dto/Plugin/ShopPluginError.php',
    'Comfino\\Extended\\Api\\Request\\NotifyAbandonedCart' => $vendorDir . '/comfino/shop-plugins-shared/src/Extended/Api/Request/NotifyAbandonedCart.php',
    'Comfino\\Extended\\Api\\Request\\NotifyShopPluginRemoval' => $vendorDir . '/comfino/shop-plugins-shared/src/Extended/Api/Request/NotifyShopPluginRemoval.php',
    'Comfino\\Extended\\Api\\Request\\ReportShopPluginError' => $vendorDir . '/comfino/shop-plugins-shared/src/Extended/Api/Request/ReportShopPluginError.php',
    'Comfino\\Extended\\Api\\Serializer\\Json' => $vendorDir . '/comfino/shop-plugins-shared/src/Extended/Api/Serializer/Json.php',
    'Comfino\\FinancialProduct\\ProductTypesListTypeEnum' => $vendorDir . '/comfino/shop-plugins-shared/src/FinancialProduct/ProductTypesListTypeEnum.php',
    'Comfino\\Main' => $baseDir . '/src/Main.php',
    'Comfino\\Order\\OrderManager' => $baseDir . '/src/Order/OrderManager.php',
    'Comfino\\Order\\ShopStatusManager' => $baseDir . '/src/Order/ShopStatusManager.php',
    'Comfino\\Order\\StatusAdapter' => $baseDir . '/src/Order/StatusAdapter.php',
    'Comfino\\PaymentGateway' => $baseDir . '/src/PaymentGateway.php',
    'Comfino\\Paywall\\PaywallViewTypeEnum' => $vendorDir . '/comfino/shop-plugins-shared/src/Paywall/PaywallViewTypeEnum.php',
    'Comfino\\Shop\\Order\\Cart' => $vendorDir . '/comfino/shop-plugins-shared/src/Shop/Order/Cart.php',
    'Comfino\\Shop\\Order\\CartInterface' => $vendorDir . '/comfino/shop-plugins-shared/src/Shop/Order/CartInterface.php',
    'Comfino\\Shop\\Order\\Cart\\CartItem' => $vendorDir . '/comfino/shop-plugins-shared/src/Shop/Order/Cart/CartItem.php',
    'Comfino\\Shop\\Order\\Cart\\CartItemInterface' => $vendorDir . '/comfino/shop-plugins-shared/src/Shop/Order/Cart/CartItemInterface.php',
    'Comfino\\Shop\\Order\\Cart\\Product' => $vendorDir . '/comfino/shop-plugins-shared/src/Shop/Order/Cart/Product.php',
    'Comfino\\Shop\\Order\\Cart\\ProductInterface' => $vendorDir . '/comfino/shop-plugins-shared/src/Shop/Order/Cart/ProductInterface.php',
    'Comfino\\Shop\\Order\\Customer' => $vendorDir . '/comfino/shop-plugins-shared/src/Shop/Order/Customer.php',
    'Comfino\\Shop\\Order\\CustomerInterface' => $vendorDir . '/comfino/shop-plugins-shared/src/Shop/Order/CustomerInterface.php',
    'Comfino\\Shop\\Order\\Customer\\Address' => $vendorDir . '/comfino/shop-plugins-shared/src/Shop/Order/Customer/Address.php',
    'Comfino\\Shop\\Order\\Customer\\AddressInterface' => $vendorDir . '/comfino/shop-plugins-shared/src/Shop/Order/Customer/AddressInterface.php',
    'Comfino\\Shop\\Order\\LoanParameters' => $vendorDir . '/comfino/shop-plugins-shared/src/Shop/Order/LoanParameters.php',
    'Comfino\\Shop\\Order\\LoanParametersInterface' => $vendorDir . '/comfino/shop-plugins-shared/src/Shop/Order/LoanParametersInterface.php',
    'Comfino\\Shop\\Order\\Order' => $vendorDir . '/comfino/shop-plugins-shared/src/Shop/Order/Order.php',
    'Comfino\\Shop\\Order\\OrderInterface' => $vendorDir . '/comfino/shop-plugins-shared/src/Shop/Order/OrderInterface.php',
    'Comfino\\Shop\\Order\\Seller' => $vendorDir . '/comfino/shop-plugins-shared/src/Shop/Order/Seller.php',
    'Comfino\\Shop\\Order\\SellerInterface' => $vendorDir . '/comfino/shop-plugins-shared/src/Shop/Order/SellerInterface.php',
    'Comfino\\TemplateRenderer\\PluginRendererStrategy' => $baseDir . '/src/TemplateRenderer/PluginRendererStrategy.php',
    'Comfino\\Tools' => $baseDir . '/src/Tools.php',
    'Comfino\\View\\Block\\PaymentGateway' => $baseDir . '/src/View/Block/PaymentGateway.php',
    'Comfino\\View\\FrontendManager' => $baseDir . '/src/View/FrontendManager.php',
    'Comfino\\View\\SettingsForm' => $baseDir . '/src/View/SettingsForm.php',
    'Comfino\\View\\TemplateManager' => $baseDir . '/src/View/TemplateManager.php',
    'Comfino\\Widget\\WidgetTypeEnum' => $vendorDir . '/comfino/shop-plugins-shared/src/Widget/WidgetTypeEnum.php',
    'Composer\\InstalledVersions' => $vendorDir . '/composer/InstalledVersions.php',
);
