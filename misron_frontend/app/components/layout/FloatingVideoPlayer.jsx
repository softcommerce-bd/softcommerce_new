'use client';

import { useState, useEffect, useRef } from 'react';
import {
  X as IoClose,
  Minimize2 as IoContract,
  Maximize2 as IoExpand,
  Play as IoPlay,
  Pause as IoPause,
  Volume2,
  VolumeX,
} from 'lucide-react';
import { useDispatch } from 'react-redux';
import { openVideoModal } from '@/store/slices/videoModalSlice';
import Link from 'next/link';
import { tkText } from '@/utils/currency';

export default function FloatingVideoPlayer({ videoUrl, productName, productData }) {
  const [isOpen, setIsOpen] = useState(true);
  const [isMinimized, setIsMinimized] = useState(false);
  const [isPlaying, setIsPlaying] = useState(false);
  const [isHovered, setIsHovered] = useState(false);
  const [isMuted, setIsMuted] = useState(true);
  const [progress, setProgress] = useState(0);
  const [isVideoReady, setIsVideoReady] = useState(false);
  const videoRef = useRef(null);
  const progressInterval = useRef(null);
  const dispatch = useDispatch();

  // Clean the video URL by removing '/public' if it exists
  const cleanVideoUrl = (url) => {
    if (!url) return url;
    // Remove '/public' from the URL path
    return url.replace('/public/', '/');
  };

  const cleanedVideoUrl = cleanVideoUrl(videoUrl);
  // console.log('Original video url:', videoUrl);
  // console.log('Cleaned video url:', cleanedVideoUrl);

  // Handle opening the video modal
  const handleOpenModal = () => {
    // Pause the floating video when opening modal
    if (videoRef.current) {
      videoRef.current.pause();
      setIsPlaying(false);
      if (progressInterval.current) {
        clearInterval(progressInterval.current);
      }
    }
    
    // Prepare video item data for the modal
    const videoItem = {
      custom_video: cleanedVideoUrl, // Use cleaned URL
      name: productName,
      thumbnail_image: productData?.thumbnail_image,
      main_price: productData?.main_price,
      slug: productData?.slug || productData?.id,
    };
    
    dispatch(openVideoModal(videoItem));
  };

  // Update progress bar
  const updateProgress = () => {
    if (progressInterval.current) {
      clearInterval(progressInterval.current);
    }
    
    progressInterval.current = setInterval(() => {
      if (videoRef.current && videoRef.current.duration && !isNaN(videoRef.current.duration)) {
        const value = (videoRef.current.currentTime / videoRef.current.duration) * 100;
        setProgress(isNaN(value) ? 0 : value);
      }
    }, 100);
  };

  // Handle progress bar change
  const handleProgressChange = (e) => {
    const newProgress = parseFloat(e.target.value);
    if (!isNaN(newProgress) && videoRef.current && videoRef.current.duration) {
      setProgress(newProgress);
      const newTime = (newProgress / 100) * videoRef.current.duration;
      if (!isNaN(newTime)) {
        videoRef.current.currentTime = newTime;
      }
    }
  };

  // Auto-play when component mounts
  useEffect(() => {
    if (videoRef.current && isOpen && !isMinimized && cleanedVideoUrl) {
      setIsVideoReady(false);
      setProgress(0);
      
      const playPromise = videoRef.current.play();
      if (playPromise !== undefined) {
        playPromise
          .then(() => {
            setIsPlaying(true);
            setIsVideoReady(true);
          })
          .catch((error) => {
            // console.log('Auto-play prevented:', error);
            setIsPlaying(false);
            setIsVideoReady(true);
          });
      }
    }

    return () => {
      if (progressInterval.current) {
        clearInterval(progressInterval.current);
      }
    };
  }, [isOpen, isMinimized, cleanedVideoUrl]);

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      if (progressInterval.current) {
        clearInterval(progressInterval.current);
      }
      if (videoRef.current) {
        videoRef.current.pause();
      }
    };
  }, []);

  const toggleMinimize = () => {
    setIsMinimized(!isMinimized);
    if (!isMinimized && videoRef.current) {
      videoRef.current.pause();
      setIsPlaying(false);
      if (progressInterval.current) {
        clearInterval(progressInterval.current);
      }
    } else if (isMinimized && videoRef.current && isOpen) {
      videoRef.current.play().catch(error => console.log('Play error:', error));
      setIsPlaying(true);
      updateProgress();
    }
  };

  const closePlayer = () => {
    if (videoRef.current) {
      videoRef.current.pause();
    }
    if (progressInterval.current) {
      clearInterval(progressInterval.current);
    }
    setIsOpen(false);
  };

  const togglePlay = () => {
    if (videoRef.current) {
      if (isPlaying) {
        videoRef.current.pause();
        if (progressInterval.current) {
          clearInterval(progressInterval.current);
        }
      } else {
        videoRef.current.play();
        updateProgress();
      }
      setIsPlaying(!isPlaying);
    }
  };

  const toggleMute = () => {
    if (videoRef.current) {
      videoRef.current.muted = !isMuted;
      setIsMuted(!isMuted);
    }
  };

  const handleManualPlay = () => {
    if (videoRef.current) {
      const playPromise = videoRef.current.play();
      if (playPromise !== undefined) {
        playPromise
          .then(() => {
            setIsPlaying(true);
            updateProgress();
          })
          .catch((error) => {
            console.log("Manual play failed:", error);
          });
      }
    }
  };

  // Format price
  const formatPrice = tkText;

  if (!isOpen || !cleanedVideoUrl) return null;

  return (
    <div 
      /* Sits above the support chat launcher, which holds the bottom-right
         corner. z-40 matches the chat, which renders later, so an open chat
         panel covers the video rather than the other way round. */
      className="fixed bottom-20 right-5 z-40 transition-all duration-300"
      onMouseEnter={() => setIsHovered(true)}
      onMouseLeave={() => setIsHovered(false)}
    >
      <div className={`
        overflow-hidden rounded-xl bg-ink shadow-e4
        transition-all duration-300
        ${isMinimized ? 'w-48 h-10' : 'w-40 md:w-40'}
        
      `}>
        {/* Header */}
        <div className="flex items-center justify-between gap-2 px-3 py-2 text-white">
          <span className="type-caption truncate font-medium text-white/80">
            {isMinimized ? 'Product Video' : `Video: ${productName || 'Product'}`}
          </span>
          <div className="flex items-center gap-2">
            {!isMinimized && (
              <button
                onClick={togglePlay}
                className="rounded-md p-1 text-white/70 transition-colors hover:bg-white/10 hover:text-white"
                title={isPlaying ? 'Pause' : 'Play'}
              >
                {isPlaying ? <IoPause size={14} /> : <IoPlay size={14} />}
              </button>
            )}
            <button
              onClick={toggleMute}
              className="rounded-md p-1 text-white/70 transition-colors hover:bg-white/10 hover:text-white"
              title={isMuted ? 'Unmute' : 'Mute'}
            >
              {isMuted ? <VolumeX size={14} aria-hidden="true" /> : <Volume2 size={14} aria-hidden="true" />}
            </button>
            <button
              onClick={toggleMinimize}
              className="rounded-md p-1 text-white/70 transition-colors hover:bg-white/10 hover:text-white"
              title={isMinimized ? 'Expand' : 'Minimize'}
            >
              {isMinimized ? <IoExpand size={14} /> : <IoContract size={14} />}
            </button>
            <button
              onClick={closePlayer}
              className="rounded-md p-1 text-white/70 transition-colors hover:bg-white/10 hover:text-white"
              title="Close"
            >
              <IoClose size={14} />
            </button>
          </div>
        </div>

        {/* Video Player */}
        {!isMinimized && (
          <div className="relative bg-ink">
            {/* Loading Indicator */}
            {!isVideoReady && (
              <div className="absolute inset-0 flex items-center justify-center bg-main/50 z-10">
                <div className="w-8 h-8 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
              </div>
            )}

            {/* Play Button Overlay (for when auto-play is blocked) */}
            {!isPlaying && isVideoReady && (
              <div 
                className="absolute inset-0 flex items-center justify-center bg-main/50 cursor-pointer z-10"
                onClick={handleManualPlay}
              >
                <div className="w-8 h-8 bg-white rounded-full flex items-center justify-center shadow-lg hover:scale-110 transition-transform">
                  <div className="w-0 h-0 border-t-8 border-t-transparent border-l-14 border-l-main border-b-8 border-b-transparent ml-1"></div>
                </div>
              </div>
            )}

            {/* Video element with click handler to open modal */}
            <div 
              className="relative cursor-pointer"
              onClick={handleOpenModal}
            >
              <video
                ref={videoRef}
                src={cleanedVideoUrl}
                muted={isMuted}
                loop
                playsInline
                className="w-full h-auto max-h-50"
                style={{ cursor: "pointer", objectFit: "cover" }}
                onTimeUpdate={updateProgress}
                onCanPlay={() => setIsVideoReady(true)}
                onPlay={() => {
                  setIsPlaying(true);
                  updateProgress();
                }}
                onPause={() => setIsPlaying(false)}
                onClick={(e) => {
                  e.stopPropagation(); // Prevent double trigger
                  handleOpenModal();
                }}
              />
              
              {/* Modal indicator overlay */}
              <div className="absolute inset-0 flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity bg-main/40 pointer-events-none">
                <div className="bg-main/80 text-white text-xs px-2 py-1 rounded-full flex items-center gap-1">
                  <IoExpand size={12} />
                  <span>Click to expand</span>
                </div>
              </div>
            </div>

            {/* Progress Bar */}
            {isVideoReady && (
              <div className="absolute bottom-0 left-0 right-0 px-2 pb-2 bg-linear-to-t from-black/50 to-transparent pt-4">
                <input
                  type="range"
                  value={progress || 0}
                  onChange={handleProgressChange}
                  min="0"
                  max="100"
                  step="0.1"
                  className="w-full progress-bar"
                  style={{
                    background: `linear-gradient(to right, white 0%, white ${progress || 0}%, #444 ${progress || 0}%, #444 100%)`,
                  }}
                />
              </div>
            )}

            {/* Product Info (shown on hover) */}
            {productData && isHovered && (
              <div className="absolute bottom-0 left-0 right-0 p-3 bg-linear-to-t from-black/90 to-transparent">
                <Link href={`/product/${productData.slug || productData.id}`} className="block" onClick={(e) => e.stopPropagation()}>
                  <div className="flex items-center gap-2">
                    <img
                      src={productData.thumbnail_image}
                      alt={productData.name}
                      className="w-10 h-14 object-cover rounded"
                    />
                    <div className="flex-1">
                      <h3 className="text-white text-xs font-semibold line-clamp-2">
                        {productData.name}
                      </h3>
                      <p className="text-red-500 text-sm font-bold mt-1">
                        {formatPrice(productData.main_price)}
                      </p>
                    </div>
                    <button className="rounded-md bg-white px-2.5 py-1 text-[0.6875rem] font-semibold text-ink transition-transform hover:scale-105">
                      View
                    </button>
                  </div>
                </Link>
              </div>
            )}
          </div>
        )}

        {/* Minimized indicator */}
        {isMinimized && (
          <div 
            className="flex h-full cursor-pointer items-center justify-center bg-ink"
            onClick={handleOpenModal}
          >
            <div className="flex items-center gap-2">
              <div className="w-2 h-2 bg-main rounded-full animate-pulse"></div>
              <span className="text-xs text-gray-300">Video playing... (Click to expand)</span>
            </div>
          </div>
        )}
      </div>

      <style jsx>{`
        .progress-bar {
          -webkit-appearance: none;
          width: 100%;
          height: 3px;
          border-radius: 5px;
          outline: none;
        }
        .progress-bar::-webkit-slider-thumb {
          -webkit-appearance: none;
          width: 10px;
          height: 10px;
          border-radius: 50%;
          background: white;
          cursor: pointer;
        }
        .progress-bar::-webkit-slider-thumb:hover {
          transform: scale(1.2);
        }
        
        @keyframes spin {
          from {
            transform: rotate(0deg);
          }
          to {
            transform: rotate(360deg);
          }
        }
        .animate-spin {
          animation: spin 1s linear infinite;
        }
        
        @keyframes pulse {
          0%, 100% {
            opacity: 1;
          }
          50% {
            opacity: 0.5;
          }
        }
        .animate-pulse {
          animation: pulse 1s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        .line-clamp-2 {
          display: -webkit-box;
          -webkit-line-clamp: 2;
          -webkit-box-orient: vertical;
          overflow: hidden;
        }
      `}</style>
    </div>
  );
}