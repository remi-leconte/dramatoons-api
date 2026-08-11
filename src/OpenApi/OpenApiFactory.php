<?php

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\OpenApi;
use ArrayObject;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\MapDecoratorArgument;

// Priorité fixée à -10 pour passer après le bundle Lexik
#[AsDecorator(decorates: 'api_platform.openapi.factory', priority: -10)]
final class OpenApiFactory implements OpenApiFactoryInterface
{
    public function __construct(
        #[MapDecoratorArgument]
        private OpenApiFactoryInterface $factory
    ) {}

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = $this->factory->__invoke($context);

        // Permet de se connecter via swagger (via le bouton "Authorize")
        $openApi = $openApi->withSecurity([['JWT' => []]]);

        $paths = $openApi->getPaths();

        // Modification de la route /login créé par Lexik
        $loginPath = '/login'; 
        $loginPathItem = $paths->getPath($loginPath);
        
        if ($loginPathItem !== null) {
            $loginPost = $loginPathItem->getPost();

            if ($loginPost !== null) {
                $newLoginPost = $loginPost->withTags(['Security']); // Modification du tag
                $paths->addPath($loginPath, $loginPathItem->withPost($newLoginPost));
            }
        }

        // Route /token/refresh
        $refreshPathItem = new PathItem(
            post: new Operation(
                operationId: 'postRefreshToken',
                tags: ['Security'],
                summary: 'Rafraîchir le token JWT',
                requestBody: new RequestBody(
                    description: 'Le refresh token généré lors du login',
                    content: new ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'refresh_token' => [
                                        'type' => 'string',
                                        'example' => 'votre_refresh_token_ici'
                                    ]
                                ],
                                'required' => ['refresh_token']
                            ]
                        ]
                    ]),
                    required: true
                ),
                responses: [
                    '200' => ['description' => 'Token rafraîchi avec succès'],
                ],
            ),
        );
        $paths->addPath('/token/refresh', $refreshPathItem);

        return $openApi;
    }
}