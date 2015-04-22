<?php

namespace KTQ\Bundle\KeymediaBundle\OpenGraph\Handler;

use Netgen\Bundle\OpenGraphBundle\Handler\FieldType\Handler;
use eZ\Publish\API\Repository\Values\Content\Field;
use KTQ\Bundle\KeyMediaBundle\FieldType\KeyMedia\Value;
use eZ\Publish\Core\Helper\FieldHelper;
use eZ\Publish\Core\Helper\TranslationHelper;
use Symfony\Component\HttpFoundation\RequestStack;
use eZ\Publish\API\Repository\Exceptions\InvalidVariationException;
use eZ\Publish\Core\MVC\Exception\SourceImageNotFoundException;
use eZ\Publish\Core\Base\Exceptions\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Netgen\Bundle\OpenGraphBundle\MetaTag\Item;
use Exception;
use KTQ\Bundle\KeyMediaBundle\Twig\KeymediaExtension;

class KeymediaHandler extends Handler
{
    /**
     * @var \KTQ\Bundle\KeyMediaBundle\Twig\KeymediaExtension
     */
    protected $keymediaExtension;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param \eZ\Publish\Core\Helper\FieldHelper $fieldHelper
     * @param \eZ\Publish\Core\Helper\TranslationHelper $translationHelper
     * @param \KTQ\Bundle\KeyMediaBundle\Twig\KeymediaExtension $keymediaExtension
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        FieldHelper $fieldHelper,
        TranslationHelper $translationHelper,
        KeymediaExtension $keymediaExtension,
        LoggerInterface $logger
    )
    {
        parent::__construct( $fieldHelper, $translationHelper );

        $this->keymediaExtension = $keymediaExtension;
        $this->logger = $logger;
    }

    /**
     * Returns if this field type handler supports current field
     *
     * @param \eZ\Publish\API\Repository\Values\Content\Field $field
     *
     * @return bool
     */
    protected function supports( Field $field )
    {
        return $field->value instanceof Value;
    }

    /**
     * Returns the field value, converted to string
     *
     * @param \eZ\Publish\API\Repository\Values\Content\Field $field
     * @param string $tagName
     * @param array $params
     *
     * @return string
     */
    protected function getFieldValue( Field $field, $tagName, array $params = array() )
    {
        if ( !$this->fieldHelper->isFieldEmpty( $this->content, $params[0] ) )
        {
            if ( !isset( $params[1] ) )
            {
                throw new InvalidArgumentException(
                    '$params[1]',
                    'Keymedia field type handler requires at least two parameters: field identifier and image format.'
                );
            }

            try
            {
                $keymediaField = $this->keymediaExtension->keyMedia(
                    $this->content,
                    $params[0],
                    array(
                        "format" => $params[1]
                    )
                );

                return 'http:' . $keymediaField["url"];
            }
            catch ( Exception $e )
            {
                if ( $this->logger !== null )
                {
                    $this->logger->error(
                        "Open Graph keymedia handler: Error while getting image with id {$field->value->id}: " . $e->getMessage()
                    );
                }
            }
        }

        return '';
    }

    /**
     * Returns the array of meta tags
     *
     * @param string $tagName
     * @param array $params
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException If number of params is incorrect
     *
     * @return \Netgen\Bundle\OpenGraphBundle\MetaTag\Item[]
     */
    public function getMetaTags( $tagName, array $params = array() )
    {
        if ( !isset( $params[0] ) )
        {
            throw new InvalidArgumentException(
                '$params[0]',
                'Field type handlers require at least a field identifier.'
            );
        }
        $field = $this->translationHelper->getTranslatedField( $this->content, $params[0] );

        if ( !$field instanceof Field )
        {
            return '';
        }

        if ( !$this->supports( $field ) )
        {
            throw new InvalidArgumentException(
                '$params[0]',
                get_class($this) . ' field type handler does not support field with identifier \'' . $field->fieldDefIdentifier . '\'.'
            );
        }

        return array(
            new Item(
                $tagName,
                $this->getFieldValue( $field, $tagName, $params )
            )
        );
    }
}