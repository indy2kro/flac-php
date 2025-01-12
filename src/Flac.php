<?php

declare(strict_types=1);

namespace bluemoehre;

use LogicException;
use RuntimeException;
use UnexpectedValueException;

/**
 * @license GNU General Public License http://www.gnu.org/licenses/licenses.html#GPL
 *
 * @author BlueMöhre <bluemoehre@gmx.de>
 *
 * @copyright 2012-2017 BlueMöhre
 *
 * @link http://www.github.com/bluemoehre
 *
 * This code is based upon the really great FLAC-Project by Josh Coalson
 * http://flac.sourceforge.net
 */
class Flac
{
    public const E_FILE_OPEN = 10;
    public const E_FILE_CLOSE = 11;
    public const E_FILE_READ = 12;
    public const E_FILE_TYPE = 13;
    public const E_METADATA_BLOCK = 20;
    public const E_METADATA_BLOCK_DATA = 21;
    public const E_PARAMETER = 30;

    public const METADATA_BLOCK_STREAMINFO = 0;
    public const METADATA_BLOCK_PADDING = 1;
    public const METADATA_BLOCK_APPLICATION = 2;
    public const METADATA_BLOCK_SEEKTABLE = 3;
    public const METADATA_BLOCK_VORBIS_COMMENT = 4;
    public const METADATA_BLOCK_CUESHEET = 5;
    public const METADATA_BLOCK_PICTURE = 6;

    public const BLOCK_SIZE_MIN = 16; // FLAC specifies a minimum block size of 16
    public const BLOCK_SIZE_MAX = 65535; // FLAC specifies a maximum block size of 65535
    public const SAMPLE_RATE_MIN = 1; // Sample rate of 0 is invalid
    public const SAMPLE_RATE_MAX = 655350;  // The maximum sample rate is limited by the structure of frame headers to 655350Hz.

    protected string $filename;

    protected int $fileSize;

    /** @var resource */
    protected mixed $fileHandle;

    /**
     * Amount of metadata blocks per type
     *
     * @var array<int, int>
     */
    protected array $metadataBlockCounts = [
        self::METADATA_BLOCK_STREAMINFO => 0,
        self::METADATA_BLOCK_PADDING => 0,
        self::METADATA_BLOCK_APPLICATION => 0,
        self::METADATA_BLOCK_SEEKTABLE => 0,
        self::METADATA_BLOCK_VORBIS_COMMENT => 0,
        self::METADATA_BLOCK_CUESHEET => 0,
        self::METADATA_BLOCK_PICTURE => 0,
    ];

    /**
     * The minimum block size (in samples) used in the stream.
     */
    protected int $streamBlockSizeMin;

    /**
     * The maximum block size (in samples) used in the stream.
     * (Minimum blocksize == maximum blocksize) implies a fixed-blocksize stream.
     */
    protected int $streamBlockSizeMax;

    /**
     * The minimum frame size (in bytes) used in the stream. May be 0 to imply the value is not known.
     */
    protected int $streamFrameSizeMin;

    /**
     * The maximum frame size (in bytes) used in the stream. May be 0 to imply the value is not known.
     */
    protected int $streamFrameSizeMax;

    protected int $sampleRate;

    protected int $channels;

    protected int $bitsPerSample;

    protected int $totalSamples;

    /**
     * Total audio length in seconds
     */
    protected float $duration;

    /**
     * MD5 signature of the unencoded audio data.
     */
    protected string $audioMd5;

    /** @var array<string, mixed> */
    protected array $vorbisComment = [];

    /**
     * @throws RuntimeException if the file could not be accessed
     * @throws UnexpectedValueException if the file is no "FLAC"
     */
    public function __construct(string $file)
    {
        $this->filename = $file;

        $fileHandle = fopen($file, 'rb');

        if ($fileHandle === false) {
            throw new RuntimeException('Cannot access file "' . $file . '"', self::E_FILE_OPEN);
        }

        $this->fileHandle = $fileHandle;

        if ($this->read(4) !== 'fLaC') {
            throw new UnexpectedValueException('Invalid file type. File is not FLAC!', self::E_FILE_TYPE);
        }

        $fileSize = filesize($file);

        if ($fileSize === false) {
            throw new RuntimeException('Cannot get file size of "' . $file . '"', self::E_FILE_READ);
        }

        $this->fileSize = $fileSize;
        $this->fetchMetadataBlocks();
    }

    /**
     * @throws RuntimeException if the file handle cannot be released
     */
    public function __destruct()
    {
        $closed = fclose($this->fileHandle);
        if ($closed === false) {
            throw new RuntimeException('Could not release file handle of "' . $this->filename . '"', self::E_FILE_CLOSE);
        }
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    /**
     * @return array<int, int>
     */
    public function getMetadataBlockCounts(): array
    {
        return $this->metadataBlockCounts;
    }

    public function getStreamBlockSizeMin(): int
    {
        return $this->streamBlockSizeMin;
    }

    public function getStreamBlockSizeMax(): int
    {
        return $this->streamBlockSizeMax;
    }

    public function getStreamFrameSizeMin(): int
    {
        return $this->streamFrameSizeMin;
    }

    public function getStreamFrameSizeMax(): int
    {
        return $this->streamFrameSizeMax;
    }

    public function getSampleRate(): int
    {
        return $this->sampleRate;
    }

    public function getChannels(): int
    {
        return $this->channels;
    }

    public function getBitsPerSample(): int
    {
        return $this->bitsPerSample;
    }

    public function getTotalSamples(): int
    {
        return $this->totalSamples;
    }

    /**
     * Audio length in seconds
     */
    public function getDuration(): float
    {
        return $this->duration;
    }

    public function getAudioMd5(): string
    {
        return $this->audioMd5;
    }

    /**
     * @return array<string, mixed>
     */
    public function getVorbisComment(): array
    {
        return $this->vorbisComment;
    }

    /**
     * Fetches all metadata
     */
    protected function fetchMetadataBlocks(): void
    {
        $isLastMetadataBlock = false;

        while (! $isLastMetadataBlock && ! feof($this->fileHandle)) {
            $metadataBlockHeader = unpack('nlast_type/X/X/Nlength', $this->read(4));

            if ($metadataBlockHeader === false) {
                throw new UnexpectedValueException('Failed to read metadata block header', self::E_METADATA_BLOCK);
            }

            $isLastMetadataBlock = (bool) ($metadataBlockHeader['last_type'] >> 15); // the first bit defines if this is the last meta block
            $metadataBlockType = $metadataBlockHeader['last_type'] >> 8 & 127; // bits 2-8 (7bit) of 16 define the block type
            $metadataBlockLength = $metadataBlockHeader['length'] & 16777215; // bits 9-32 (24bit) of 32 define block length

            // Streaminfo
            if ($metadataBlockType === self::METADATA_BLOCK_STREAMINFO) {
                if (array_sum($this->metadataBlockCounts) > 0) {
                    throw new UnexpectedValueException('METADATA_BLOCK_STREAMINFO must be the first metadata block', self::E_METADATA_BLOCK);
                }

                if ($this->metadataBlockCounts[self::METADATA_BLOCK_STREAMINFO] > 0) {
                    throw new UnexpectedValueException('METADATA_BLOCK_STREAMINFO must occur only once', self::E_METADATA_BLOCK);
                }

                $metadataBlockData = unpack(
                    'nminBlockSize/nmaxBlockSize/NminFrameSize/X/NmaxFrameSize/X/N2samplerate_channels_bitrate_samples/H32md5',
                    $this->read($metadataBlockLength)
                );

                if ($metadataBlockData === false) {
                    throw new UnexpectedValueException('Failed to read metadata block data', self::E_METADATA_BLOCK);
                }

                $metadataBlockData['samplerate_channels_bitrate_samples'] = $metadataBlockData['samplerate_channels_bitrate_samples1'] << 32 | $metadataBlockData['samplerate_channels_bitrate_samples2'];
                $sampleRate = $metadataBlockData['samplerate_channels_bitrate_samples'] >> 44;

                if ($metadataBlockData['minBlockSize'] < self::BLOCK_SIZE_MIN) {
                    throw new UnexpectedValueException(
                        sprintf('Minimum block size of %d is less than the allowed minimum of %d', $metadataBlockData['minBlockSize'], self::BLOCK_SIZE_MIN),
                        self::E_METADATA_BLOCK_DATA
                    );
                }

                if ($metadataBlockData['maxBlockSize'] > self::BLOCK_SIZE_MAX) {
                    throw new UnexpectedValueException(
                        sprintf('Maximum block size of %d is more than the allowed maximum of %d', $metadataBlockData['maxBlockSize'], self::BLOCK_SIZE_MAX),
                        self::E_METADATA_BLOCK_DATA
                    );
                }

                if ($metadataBlockData['minBlockSize'] > $metadataBlockData['maxBlockSize']) {
                    throw new UnexpectedValueException(
                        sprintf('Minimum block size of %d must not be more than maximum block size of %d', $metadataBlockData['minBlockSize'], $metadataBlockData['maxBlockSize']),
                        self::E_METADATA_BLOCK_DATA
                    );
                }

                if ($sampleRate < self::SAMPLE_RATE_MIN || $sampleRate > self::SAMPLE_RATE_MAX) {
                    throw new UnexpectedValueException(
                        sprintf('Sample rate of %d is invalid. It must be within the range of %d-%d.', $sampleRate, self::SAMPLE_RATE_MIN, self::SAMPLE_RATE_MAX),
                        self::E_METADATA_BLOCK_DATA
                    );
                }

                if (! preg_match('/^[0-9a-f]{32}$/', $metadataBlockData['md5'])) {
                    throw new UnexpectedValueException('Invalid MD5 hash', self::E_METADATA_BLOCK_DATA);
                }

                $this->metadataBlockCounts[self::METADATA_BLOCK_STREAMINFO]++;
                $this->streamBlockSizeMin = $metadataBlockData['minBlockSize'];
                $this->streamBlockSizeMax = $metadataBlockData['maxBlockSize'];
                $this->streamFrameSizeMin = $metadataBlockData['minFrameSize'] >> 8;
                $this->streamFrameSizeMax = $metadataBlockData['maxFrameSize'] >> 8;
                $this->sampleRate = $sampleRate;
                $this->channels = ($metadataBlockData['samplerate_channels_bitrate_samples'] >> 41 & 7) + 1;
                $this->bitsPerSample = ($metadataBlockData['samplerate_channels_bitrate_samples'] >> 36 & 31) + 1;
                $this->totalSamples = $metadataBlockData['samplerate_channels_bitrate_samples'] & 68719476735;
                $this->duration = $this->totalSamples / $this->sampleRate;
                $this->audioMd5 = $metadataBlockData['md5'];
            } elseif ($metadataBlockType === self::METADATA_BLOCK_PADDING) { // Padding
                $this->metadataBlockCounts[self::METADATA_BLOCK_PADDING]++;
                fseek($this->fileHandle, $metadataBlockLength, SEEK_CUR);
            } elseif ($metadataBlockType === self::METADATA_BLOCK_APPLICATION) { // Application
                $this->metadataBlockCounts[self::METADATA_BLOCK_APPLICATION]++;
                fseek($this->fileHandle, $metadataBlockLength, SEEK_CUR);
            } elseif ($metadataBlockType === self::METADATA_BLOCK_SEEKTABLE) { // Seektable
                $this->metadataBlockCounts[self::METADATA_BLOCK_SEEKTABLE]++;
                fseek($this->fileHandle, $metadataBlockLength, SEEK_CUR);
            } elseif ($metadataBlockType === self::METADATA_BLOCK_VORBIS_COMMENT) { // Vorbis Comment
                $this->metadataBlockCounts[self::METADATA_BLOCK_VORBIS_COMMENT]++;
                $this->vorbisComment = [];

                $metadataBlockRaw = $this->read($metadataBlockLength);
                $rawPosition = 0;

                $metadataBlockData = unpack('V', substr($metadataBlockRaw, $rawPosition, 4));

                if ($metadataBlockData === false) {
                    throw new UnexpectedValueException('Failed to read Vorbis block data', self::E_METADATA_BLOCK);
                }

                $this->vorbisComment['vendorLength'] = $metadataBlockData[1];
                $rawPosition += 4;

                $this->vorbisComment['vendorString'] = substr($metadataBlockRaw, $rawPosition, $this->vorbisComment['vendorLength']);
                $rawPosition += $this->vorbisComment['vendorLength'];

                $metadataBlockData = unpack('V', substr($metadataBlockRaw, $rawPosition, 4));

                if ($metadataBlockData === false) {
                    throw new UnexpectedValueException('Failed to read Vorbis block data', self::E_METADATA_BLOCK);
                }

                $commentsLength = $metadataBlockData[1];
                $rawPosition += 4;

                for ($i = 0; $i < $commentsLength; $i++) {
                    $metadataBlockData = unpack('V', substr($metadataBlockRaw, $rawPosition, 4));

                    if ($metadataBlockData === false) {
                        throw new UnexpectedValueException('Failed to read Vorbis block raw comments', self::E_METADATA_BLOCK);
                    }

                    $commentSize = $metadataBlockData[1];
                    $rawPosition += 4;

                    $comment = substr($metadataBlockRaw, $rawPosition, $commentSize);
                    $rawPosition += $commentSize;

                    $delimiterPosition = strpos($comment, '=');
                    if ($delimiterPosition === false) {
                        throw new UnexpectedValueException('Vorbis comment must contain "=" as delimiter', self::E_METADATA_BLOCK_DATA);
                    }

                    $field = strtolower(substr($comment, 0, $delimiterPosition));
                    $value = substr($comment, $delimiterPosition + 1);

                    $this->vorbisComment['comments'][$field][] = $value;
                }
            } elseif ($metadataBlockType === self::METADATA_BLOCK_CUESHEET) { // Cuesheet
                $this->metadataBlockCounts[self::METADATA_BLOCK_CUESHEET] += 1;
                fseek($this->fileHandle, $metadataBlockLength, SEEK_CUR);
            } elseif ($metadataBlockType === self::METADATA_BLOCK_PICTURE) { // Picture
                $this->metadataBlockCounts[self::METADATA_BLOCK_PICTURE] += 1;
                fseek($this->fileHandle, $metadataBlockLength, SEEK_CUR);
            } elseif ($metadataBlockType > 126) {
                throw new UnexpectedValueException(
                    sprintf('Invalid metadata block type: %d', $metadataBlockType),
                    self::E_METADATA_BLOCK
                );
            }
        }
    }

    /**
     * Reads $length bytes from the filename
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    protected function read(int $length): string
    {
        if ($length < 1) {
            throw new LogicException('Argument must be positive integer', self::E_PARAMETER);
        }

        $data = fread($this->fileHandle, $length);

        if ($data === false) {
            throw new RuntimeException('Cannot not read from filename', self::E_FILE_READ);
        }

        return $data;
    }
}
