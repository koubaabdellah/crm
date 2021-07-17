<?php

namespace Oro\Bundle\ContactBundle\Form\Type;

use Oro\Bundle\AddressBundle\Form\Type\AddressCollectionType;
use Oro\Bundle\AddressBundle\Form\Type\EmailCollectionType;
use Oro\Bundle\AddressBundle\Form\Type\EmailType;
use Oro\Bundle\AddressBundle\Form\Type\PhoneCollectionType;
use Oro\Bundle\AddressBundle\Form\Type\PhoneType;
use Oro\Bundle\AddressBundle\Form\Type\TypedAddressType;
use Oro\Bundle\AttachmentBundle\Form\Type\ImageType;
use Oro\Bundle\ContactBundle\Entity\Contact;
use Oro\Bundle\FormBundle\Form\Type\EntityIdentifierType;
use Oro\Bundle\FormBundle\Form\Type\OroBirthdayType;
use Oro\Bundle\FormBundle\Form\Type\OroResizeableRichTextType;
use Oro\Bundle\TranslationBundle\Form\Type\TranslatableEntityType;
use Oro\Bundle\UserBundle\Form\Type\GenderType;
use Oro\Bundle\UserBundle\Form\Type\OrganizationUserAclSelectType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->buildPlainFields($builder, $options);
        $this->buildRelationFields($builder, $options);

        // set predefined accounts in case of creating a new contact
        $builder->addEventListener(
            FormEvents::POST_SET_DATA,
            function (FormEvent $event) {
                $contact = $event->getData();
                if ($contact && $contact instanceof Contact && !$contact->getId() && $contact->hasAccounts()) {
                    $form = $event->getForm();
                    $form->get('appendAccounts')->setData($contact->getAccounts());
                }
            }
        );
    }

    protected function buildPlainFields(FormBuilderInterface $builder, array $options)
    {
        // basic plain fields
        $builder
            ->add('namePrefix', TextType::class, array('required' => false, 'label' => 'oro.contact.name_prefix.label'))
            ->add('firstName', TextType::class, array('required' => false, 'label' => 'oro.contact.first_name.label'))
            ->add('middleName', TextType::class, array('required' => false, 'label' => 'oro.contact.middle_name.label'))
            ->add('lastName', TextType::class, array('required' => false, 'label' => 'oro.contact.last_name.label'))
            ->add('nameSuffix', TextType::class, array('required' => false, 'label' => 'oro.contact.name_suffix.label'))
            ->add('gender', GenderType::class, array('required' => false, 'label' => 'oro.contact.gender.label'))
            ->add(
                'birthday',
                OroBirthdayType::class,
                array('required' => false, 'label' => 'oro.contact.birthday.label')
            )
            ->add(
                'description',
                OroResizeableRichTextType::class,
                array(
                    'required' => false,
                    'label' => 'oro.contact.description.label'
                )
            );

        $builder
            ->add('jobTitle', TextType::class, array('required' => false, 'label' => 'oro.contact.job_title.label'))
            ->add('fax', TextType::class, array('required' => false, 'label' => 'oro.contact.fax.label'))
            ->add('skype', TextType::class, array('required' => false, 'label' => 'oro.contact.skype.label'));

        $builder
            ->add('twitter', TextType::class, array('required' => false, 'label' => 'oro.contact.twitter.label'))
            ->add('facebook', TextType::class, array('required' => false, 'label' => 'oro.contact.facebook.label'))
            ->add('googlePlus', TextType::class, array('required' => false, 'label' => 'oro.contact.google_plus.label'))
            ->add('linkedIn', TextType::class, array('required' => false, 'label' => 'oro.contact.linked_in.label'))
            ->add(
                'picture',
                ImageType::class,
                array(
                    'label'          => 'oro.contact.picture.label',
                    'required'       => false
                )
            );
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function buildRelationFields(FormBuilderInterface $builder, array $options)
    {
        // contact source
        $builder->add(
            'source',
            TranslatableEntityType::class,
            array(
                'label'        => 'oro.contact.source.label',
                'class'        => 'OroContactBundle:Source',
                'choice_label' => 'label',
                'required'     => false,
                'placeholder'  => false,
            )
        );

        // assigned to (user)
        $builder->add(
            'assignedTo',
            OrganizationUserAclSelectType::class,
            array('required' => false, 'label' => 'oro.contact.assigned_to.label')
        );

        // reports to (contact)
        $builder->add(
            'reportsTo',
            ContactSelectType::class,
            array('required' => false, 'label' => 'oro.contact.reports_to.label')
        );

        // contact method
        $builder->add(
            'method',
            TranslatableEntityType::class,
            array(
                'label'        => 'oro.contact.method.label',
                'class'        => 'OroContactBundle:Method',
                'choice_label' => 'label',
                'required'     => false,
                'placeholder'  => 'oro.contact.form.choose_contact_method'
            )
        );

        // addresses, emails and phones
        $builder->add(
            'addresses',
            AddressCollectionType::class,
            array(
                'label'    => '',
                'entry_type' => TypedAddressType::class,
                'required' => true,
                'entry_options'  => array('data_class' => 'Oro\Bundle\ContactBundle\Entity\ContactAddress')
            )
        );
        $builder->add(
            'emails',
            EmailCollectionType::class,
            array(
                'label'    => 'oro.contact.emails.label',
                'entry_type'     => EmailType::class,
                'required' => false,
                'entry_options'  => array('data_class' => 'Oro\Bundle\ContactBundle\Entity\ContactEmail')
            )
        );
        $builder->add(
            'phones',
            PhoneCollectionType::class,
            array(
                'label'    => 'oro.contact.phones.label',
                'entry_type'     => PhoneType::class,
                'required' => false,
                'entry_options'  => array('data_class' => 'Oro\Bundle\ContactBundle\Entity\ContactPhone')
            )
        );

        // groups
        $builder->add(
            'groups',
            EntityType::class,
            array(
                'label'    => 'oro.contact.groups.label',
                'class'    => 'OroContactBundle:Group',
                'choice_label' => 'label',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'translatable_options' => false
            )
        );

        // accounts
        $builder->add(
            'appendAccounts',
            EntityIdentifierType::class,
            array(
                'class'    => 'OroAccountBundle:Account',
                'required' => false,
                'mapped'   => false,
                'multiple' => true,
            )
        );
        $builder->add(
            'removeAccounts',
            EntityIdentifierType::class,
            array(
                'class'    => 'OroAccountBundle:Account',
                'required' => false,
                'mapped'   => false,
                'multiple' => true,
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            array(
                'data_class'           => 'Oro\Bundle\ContactBundle\Entity\Contact',
                'csrf_token_id'        => 'contact',
            )
        );
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        /** @var Contact $contact */
        $contact                                       = $form->getData();
        $view->children['reportsTo']->vars['excluded'] = array_merge(
            $view->children['reportsTo']->vars['excluded'],
            array($contact->getId())
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->getBlockPrefix();
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'oro_contact';
    }
}
