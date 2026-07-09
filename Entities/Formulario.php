<?php

namespace FormularioDinamico\Entities;

use Doctrine\ORM\Mapping as ORM;

/**
 * FormularioDinamico
 *
 * @ORM\Table(name="formulario_dinamico",
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="idx_fd_slug", columns={"slug"})
 *     },
 *     indexes={
 *         @ORM\Index(name="idx_fd_entidade", columns={"entidade"}),
 *         @ORM\Index(name="idx_fd_ativo", columns={"ativo"})
 *     }
 * )
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks
 */
class Formulario extends \MapasCulturais\Entity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     * @ORM\SequenceGenerator(sequenceName="formulario_dinamico_id_seq", allocationSize=1, initialValue=1)
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="slug", type="string", length=100, nullable=false, unique=true)
     */
    protected $slug;

    /**
     * @var string
     *
     * @ORM\Column(name="titulo", type="string", length=255, nullable=false)
     */
    protected $titulo;

    /**
     * @var string|null
     *
     * @ORM\Column(name="descricao", type="text", nullable=true)
     */
    protected $descricao;

    /**
     * @var string
     *
     * @ORM\Column(name="entidade", type="string", length=20, nullable=false)
     */
    protected $entidade;

    /**
     * @var boolean
     *
     * @ORM\Column(name="ativo", type="boolean", nullable=false, options={"default": true})
     */
    protected $ativo = true;

    /**
     * @var \MapasCulturais\Entities\Agent|null
     *
     * @ORM\ManyToOne(targetEntity="MapasCulturais\Entities\Agent")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="criado_por", referencedColumnName="id")
     * })
     */
    protected $criadoPor;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="criado_em", type="datetime", nullable=false)
     */
    protected $criadoEm;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="atualizado_em", type="datetime", nullable=true)
     */
    protected $atualizadoEm;

    /**
     * @ORM\PrePersist
     */
    public function onPrePersist()
    {
        $this->criadoEm = new \DateTime();
        $this->atualizadoEm = new \DateTime();
    }

    /**
     * @ORM\PreUpdate
     */
    public function onPreUpdate()
    {
        $this->atualizadoEm = new \DateTime();
    }

    // ================================================================
    // Getters e Setters
    // ================================================================

    public function getId(): int { return $this->id; }

    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): void { $this->slug = $slug; }

    public function getTitulo(): string { return $this->titulo; }
    public function setTitulo(string $titulo): void { $this->titulo = $titulo; }

    public function getDescricao(): ?string { return $this->descricao; }
    public function setDescricao(?string $descricao): void { $this->descricao = $descricao; }

    public function getEntidade(): string { return $this->entidade; }
    public function setEntidade(string $entidade): void { $this->entidade = $entidade; }

    public function getAtivo(): bool { return $this->ativo; }
    public function setAtivo(bool $ativo): void { $this->ativo = $ativo; }

    public function getCriadoPor(): ?\MapasCulturais\Entities\Agent { return $this->criadoPor; }
    public function setCriadoPor(?\MapasCulturais\Entities\Agent $agent): void { $this->criadoPor = $agent; }

    public function getCriadoEm(): \DateTime { return $this->criadoEm; }
    public function getAtualizadoEm(): ?\DateTime { return $this->atualizadoEm; }
}
