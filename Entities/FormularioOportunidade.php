<?php

namespace FormularioDinamico\Entities;

use Doctrine\ORM\Mapping as ORM;

/**
 * FormularioDinamicoOportunidade
 *
 * @ORM\Table(name="formulario_dinamico_oportunidade",
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="idx_fdo_uniq", columns={"formulario_id", "oportunidade_id"})
 *     }
 * )
 * @ORM\Entity
 */
class FormularioOportunidade extends \MapasCulturais\Entity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="formulario_dinamico_oportunidade_id_seq", allocationSize=1, initialValue=1)
     */
    protected $id;

    /**
     * @var \FormularioDinamico\Entities\Formulario
     *
     * @ORM\ManyToOne(targetEntity="FormularioDinamico\Entities\Formulario")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="formulario_id", referencedColumnName="id", onDelete="CASCADE")
     * })
     */
    protected $formulario;

    /**
     * @var \MapasCulturais\Entities\Opportunity
     *
     * @ORM\ManyToOne(targetEntity="MapasCulturais\Entities\Opportunity")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="oportunidade_id", referencedColumnName="id", onDelete="CASCADE")
     * })
     */
    protected $oportunidade;

    // ================================================================
    // Getters e Setters
    // ================================================================

    public function getId(): int { return $this->id; }

    public function getFormulario(): Formulario { return $this->formulario; }
    public function setFormulario(Formulario $formulario): void { $this->formulario = $formulario; }

    public function getOportunidade(): \MapasCulturais\Entities\Opportunity { return $this->oportunidade; }
    public function setOportunidade(\MapasCulturais\Entities\Opportunity $oportunidade): void { $this->oportunidade = $oportunidade; }
}
