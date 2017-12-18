<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Api\Entity\Field;

use Shopware\Api\Entity\Write\FieldAware\PathAware;
use Shopware\Api\Entity\Write\FieldAware\StorageAware;
use Shopware\Api\Entity\Write\FieldAware\ValidatorAware;
use Shopware\Api\Entity\Write\FieldAware\WriteContextAware;
use Shopware\Api\Entity\Write\FieldException\InvalidFieldException;
use Shopware\Api\Entity\Write\WriteContext;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FkField extends Field implements WriteContextAware, ValidatorAware, PathAware, StorageAware
{
    /**
     * @var string
     */
    private $storageName;

    /**
     * @var WriteContext
     */
    private $writeContext;

    /**
     * @var string
     */
    private $referenceClass;

    /**
     * @var string
     */
    private $referenceField;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var string
     */
    private $path;

    public function __construct(string $storageName, string $propertyName, string $referenceClass, string $referenceField = 'uuid')
    {
        $this->referenceClass = $referenceClass;
        $this->storageName = $storageName;
        $this->referenceField = $referenceField;
        parent::__construct($propertyName);
    }

    public function __invoke(string $type, string $key, $value = null): \Generator
    {
        if (!$value) {
            try {
                $value = $this->writeContext->get($this->referenceClass, $this->referenceField);
            } catch (\InvalidArgumentException $exception) {
                $this->validate($key, $value);
            }
        }

        yield $this->storageName => $value;
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    public function setWriteContext(WriteContext $writeContext): void
    {
        $this->writeContext = $writeContext;
    }

    public function setValidator(ValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }

    public function setPath(string $path = ''): void
    {
        $this->path = $path;
    }

    /**
     * @param string $fieldName
     * @param $value
     *
     * @throws InvalidFieldException
     */
    private function validate(string $fieldName, $value): void
    {
        $violationList = new ConstraintViolationList();
        $violations = $this->validator->validate($value, [new NotBlank()]);

        /** @var ConstraintViolation $violation */
        foreach ($violations as $violation) {
            $violationList->add(
                new ConstraintViolation(
                    $violation->getMessage(),
                    $violation->getMessageTemplate(),
                    $violation->getParameters(),
                    $violation->getRoot(),
                    $fieldName,
                    $violation->getInvalidValue(),
                    $violation->getPlural(),
                    $violation->getCode(),
                    $violation->getConstraint(),
                    $violation->getCause()
                )
            );
        }

        if (count($violationList)) {
            throw new InvalidFieldException($this->path . '/' . $fieldName, $violationList);
        }
    }
}
