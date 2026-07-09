<?php

namespace FormularioDinamico\Entities;

use Doctrine\ORM\Mapping as ORM;

/**
 * FormularioDinamicoCampo
 *
 * @ORM\Table(name="formulario_dinamico_campo",
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="idx_fdc_fk_slug", columns={"formulario_id", "slug"})
 *     },
 *     indexes={
 *         @ORM\Index(name="idx_fdc_ordem", columns={"formulario_id", "ordem"})
 *     }
 * )
 * @ORM\Entity
 */
class FormularioCampo extends \MapasCulturais\Entity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="formulario_dinamico_campo_id_seq", allocationSize=1, initialValue=1)
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
     * @var string
     *
     * @ORM\Column(name="slug", type="string", length=100, nullable=false)
     */
    protected $slug;

    /**
     * @var string
     *
     * @ORM\Column(name="rotulo", type="string", length=255, nullable=false)
     */
    protected $rotulo;

    /**
     * @var string|null
     *
     * @ORM\Column(name="placeholder", type="string", length=255, nullable=true)
     */
    protected $placeholder;

    /**
     * @var string
     *
     * @ORM\Column(name="tipo", type="string", length=30, nullable=false)
     */
    protected $tipo;

    /**
     * @var array|null
     *
     * @ORM\Column(name="opcoes", type="json", nullable=true)
     */
    protected $opcoes;

    /**
     * @var boolean
     *
     * @ORM\Column(name="obrigatorio", type="boolean", nullable=false, options={"default": false})
     */
    protected $obrigatorio = false;

    /**
     * @var integer
     *
     * @ORM\Column(name="ordem", type="integer", nullable=false, options={"default": 0})
     */
    protected $ordem = 0;

    /**
     * @var integer
     *
     * @ORM\Column(name="coluna_span", type="integer", nullable=false, options={"default": 12})
     */
    protected $colunaSpan = 12;

    /**
     * @var boolean
     *
     * @ORM\Column(name="editavel", type="boolean", nullable=false, options={"default": true})
     */
    protected $editavel = true;

    // ================================================================
    // Getters e Setters
    // ================================================================

    public function getId(): int { return $this->id; }

    public function getFormulario(): Formulario { return $this->formulario; }
    public function setFormulario(Formulario $formulario): void { $this->formulario = $formulario; }

    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): void { $this->slug = $slug; }

    public function getRotulo(): string { return $this->rotulo; }
    public function setRotulo(string $rotulo): void { $this->rotulo = $rotulo; }

    public function getPlaceholder(): ?string { return $this->placeholder; }
    public function setPlaceholder(?string $placeholder): void { $this->placeholder = $placeholder; }

    public function getTipo(): string { return $this->tipo; }
    public function setTipo(string $tipo): void { $this->tipo = $tipo; }

    public function getOpcoes(): ?array { return $this->opcoes; }
    public function setOpcoes(?array $opcoes): void { $this->opcoes = $opcoes; }

    public function getObrigatorio(): bool { return $this->obrigatorio; }
    public function setObrigatorio(bool $obrigatorio): void { $this->obrigatorio = $obrigatorio; }

    public function getOrdem(): int { return $this->ordem; }
    public function setOrdem(int $ordem): void { $this->ordem = $ordem; }

    public function getColunaSpan(): int { return $this->colunaSpan; }
    public function setColunaSpan(int $colunaSpan): void { $this->colunaSpan = $colunaSpan; }

    public function getEditavel(): bool { return $this->editavel; }
    public function setEditavel(bool $editavel): void { $this->editavel = $editavel; }
}
