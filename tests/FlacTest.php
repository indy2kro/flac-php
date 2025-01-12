<?php

declare(strict_types=1);

namespace bluemoehre\Test;

use bluemoehre\Flac;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FlacTest extends TestCase
{
    public function testNonExistent(): void
    {
        $this->expectException(RuntimeException::class);
        new Flac('fixtures/non-existent-file');
    }

    public function testInvalidFlac(): void
    {
        $this->expectException(RuntimeException::class);
        new Flac('fixtures/invalid.flac');
    }

    public function testGetters(): void
    {
        $filename = 'fixtures/44100Hz-16bit-1ch.flac';
        $flac = new Flac($filename);
        $this->assertSame($filename, $flac->getFilename());
        $this->assertSame(67590, $flac->getFileSize());
        $this->assertSame([1, 0, 0, 0, 1, 0, 0], $flac->getMetadataBlockCounts());
        $this->assertSame(4096, $flac->getStreamBlockSizeMin());
        $this->assertSame(4096, $flac->getStreamBlockSizeMax());
        $this->assertSame(4328, $flac->getStreamFrameSizeMin());
        $this->assertSame(6470, $flac->getStreamFrameSizeMax());
        $this->assertSame(44100, $flac->getSampleRate());
        $this->assertSame(1, $flac->getChannels());
        $this->assertSame(16, $flac->getBitsPerSample());
        $this->assertSame(44100, $flac->getTotalSamples());
        $this->assertEqualsWithDelta(1.0, $flac->getDuration(), PHP_FLOAT_EPSILON);
        $this->assertSame('874465dc8789a3047d91ffd456c185cf', $flac->getAudioMd5());
        $comments = $flac->getVorbisComment();
        $this->assertSame(32, $comments['vendorLength']);
        $this->assertSame('reference libFLAC 1.3.1 20141125', $comments['vendorString']);
        $this->assertIsArray($comments['comments']);
        $this->assertCount(3, $comments['comments']);
        $this->assertSame(['Chirp / Square (non aliased)'], $comments['comments']['title']);
        $this->assertSame(['2017'], $comments['comments']['date']);
        $this->assertSame(['Generator'], $comments['comments']['artist']);
    }

    public function testFixtures(): void
    {
        $flac = new Flac('fixtures/44100Hz-16bit-1ch.flac');
        $this->assertSame(67590, $flac->getFileSize(), 'Filesize should be 67.590 Bytes');
        $this->assertEqualsWithDelta(1.0, $flac->getDuration(), PHP_FLOAT_EPSILON, 'Duration should be 1sec');
        $this->assertSame(44100, $flac->getSampleRate(), 'Sample rate should be 44.1KHz');
        $this->assertSame(1, $flac->getChannels(), 'Channel count should be 1');
        $this->assertSame(16, $flac->getBitsPerSample());
        $this->assertSame('874465dc8789a3047d91ffd456c185cf', $flac->getAudioMd5());
        $this->assertSame(44100, $flac->getTotalSamples());
        $this->assertSame(
            [
                'vendorLength' => 32,
                'vendorString' => 'reference libFLAC 1.3.1 20141125',
                'comments' => [
                    'title' => [
                        'Chirp / Square (non aliased)',
                    ],
                    'date' => [
                        '2017',
                    ],
                    'artist' => [
                        'Generator',
                    ],
                ],
            ],
            $flac->getVorbisComment()
        );
//        Embedded cuesheet : no

        $flac = new Flac('fixtures/44100Hz-24bit-1ch.flac');
        $this->assertSame(111775, $flac->getFileSize(), 'Filesize should be 111.775 Bytes');
        $this->assertEqualsWithDelta(1.0, $flac->getDuration(), PHP_FLOAT_EPSILON, 'Duration should be 1sec');
        $this->assertSame(44100, $flac->getSampleRate(), 'Sample rate should be 44.1KHz');
        $this->assertSame(1, $flac->getChannels(), 'Channel count should be 1');
        $this->assertSame(24, $flac->getBitsPerSample());
        $this->assertSame('036e068f773b5bbe31d722c70350bf9e', $flac->getAudioMd5());
        $this->assertSame(44100, $flac->getTotalSamples());
        $this->assertSame(
            [
                'vendorLength' => 32,
                'vendorString' => 'reference libFLAC 1.3.1 20141125',
                'comments' => [
                    'title' => [
                        'Chirp / Square (non aliased)',
                    ],
                    'date' => [
                        '2017',
                    ],
                    'artist' => [
                        'Generator',
                    ],
                ],
            ],
            $flac->getVorbisComment()
        );
//        Embedded cuesheet : no

        $flac = new Flac('fixtures/192000Hz-16bit-2ch.flac');
        $this->assertSame(492615, $flac->getFileSize(), 'Filesize should be 492.615 Bytes');
        $this->assertEqualsWithDelta(1.0, $flac->getDuration(), PHP_FLOAT_EPSILON, 'Duration should be 1sec');
        $this->assertSame(192000, $flac->getSampleRate(), 'Sample rate should be 192KHz');
        $this->assertSame(2, $flac->getChannels(), 'Channel count should be 2');
        $this->assertSame(16, $flac->getBitsPerSample());
        $this->assertSame('92cae790983d8e8e0d811f5b11118ba3', $flac->getAudioMd5());
        $this->assertSame(192000, $flac->getTotalSamples());
        $this->assertSame(
            [
                'vendorLength' => 32,
                'vendorString' => 'reference libFLAC 1.3.1 20141125',
                'comments' => [
                    'title' => [
                        'Chirp / Sawtooth',
                    ],
                    'date' => [
                        '2017',
                    ],
                    'artist' => [
                        'Generator',
                    ],
                ],
            ],
            $flac->getVorbisComment()
        );
//        Embedded cuesheet : no

        $flac = new Flac('fixtures/192000Hz-24bit-2ch.flac');
        $this->assertSame(883514, $flac->getFileSize(), 'Filesize should be 883.514 Bytes');
        $this->assertEqualsWithDelta(1.0, $flac->getDuration(), PHP_FLOAT_EPSILON, 'Duration should be 1sec');
        $this->assertSame(192000, $flac->getSampleRate(), 'Sample rate should be 192KHz');
        $this->assertSame(2, $flac->getChannels(), 'Channel count should be 2');
        $this->assertSame(24, $flac->getBitsPerSample());
        $this->assertSame('270f45a5aa5b75dfb6acaa806eb08ba6', $flac->getAudioMd5());
        $this->assertSame(192000, $flac->getTotalSamples());
        $this->assertSame(
            [
                'vendorLength' => 32,
                'vendorString' => 'reference libFLAC 1.3.1 20141125',
                'comments' => [
                    'title' => [
                        'Chirp / Sawtooth',
                    ],
                    'date' => [
                        '2017',
                    ],
                    'artist' => [
                        'Generator',
                    ],
                ],
            ],
            $flac->getVorbisComment()
        );
//        Embedded cuesheet : no
    }
}
