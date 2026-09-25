<?php

namespace App\Service;

use App\Entity\Document;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Guarda los archivos subidos en disco (fuera de public/) y crea la entidad
 * Document con solo la referencia y los metadatos.
 */
class DocumentStorage
{
    /** Extensiones admitidas. */
    public const array ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'];
    public const int MAX_BYTES = 10 * 1024 * 1024; // 10 MB

    /**
     * MIME reales (detectados por contenido con fileinfo) aceptables por extensión.
     * Estricto en los tipos que se sirven inline (pdf/jpg/png): así un HTML/SVG/PHP
     * renombrado a .pdf se rechaza. Permisivo con Office (zip/OLE/octet-stream son
     * detecciones normales) para no rechazar archivos legítimos; no se sirven inline.
     *
     * @var array<string, list<string>>
     */
    private const array MIME_WHITELIST = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%/storage')] private readonly string $baseDir,
    ) {
    }

    /**
     * Valida y almacena el archivo. Lanza \RuntimeException con un mensaje en
     * español si el archivo no es válido.
     */
    public function store(UploadedFile $file): Document
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (!\in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \RuntimeException('Tipo de archivo no permitido. Admitidos: '.implode(', ', self::ALLOWED_EXTENSIONS).'.');
        }
        if ($file->getSize() > self::MAX_BYTES) {
            throw new \RuntimeException('El archivo supera el tamaño máximo de 10 MB.');
        }

        // Comprobar el MIME REAL (por contenido), no solo la extensión: evita subir
        // p. ej. un HTML/script renombrado a .pdf. Se hace antes de mover el temporal.
        $realMime = $file->getMimeType();
        if (null === $realMime || !\in_array($realMime, self::MIME_WHITELIST[$extension], true)) {
            throw new \RuntimeException('El contenido del archivo no coincide con su extensión.');
        }

        $relativeDir = date('Y').'/'.date('m');
        $filename = bin2hex(random_bytes(16)).'.'.$extension;
        $targetDir = $this->baseDir.'/'.$relativeDir;

        try {
            $file->move($targetDir, $filename);
        } catch (FileException $e) {
            throw new \RuntimeException('No se pudo guardar el archivo.', 0, $e);
        }

        $document = new Document();
        $document->setOriginalName($file->getClientOriginalName());
        $document->setStoragePath($relativeDir.'/'.$filename);
        $document->setMimeType($this->mimeFor($extension));
        $document->setSizeBytes((int) filesize($targetDir.'/'.$filename));

        return $document;
    }

    public function absolutePath(Document $document): string
    {
        return $this->baseDir.'/'.$document->getStoragePath();
    }

    public function remove(Document $document): void
    {
        $path = $this->absolutePath($document);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function mimeFor(string $extension): string
    {
        return match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/octet-stream',
        };
    }
}
