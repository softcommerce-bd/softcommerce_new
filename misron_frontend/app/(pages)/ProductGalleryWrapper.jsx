"use client";

import { useState, useMemo } from "react";
import ImageGallery from "./ImageGallery";
import Details from "./Details";

function parseColorPrefix(variantStr) {
  if (!variantStr) return "";
  const parts = variantStr.split("-");
  if (parts.length <= 1) return variantStr;
  return parts.slice(0, -1).join("-");
}

function getColorNameByCode(colorsName, code) {
  const found = colorsName.find((c) => c.code === code);
  return found ? found.name : null;
}

export default function ProductGalleryWrapper({ productData, galleryImages }) {
  const [selectedColor, setSelectedColor] = useState(null);
  const [selectedVariant, setSelectedVariant] = useState(null);

  const colorsName = productData.colors_name || [];
  const photos = productData.photos || [];

  const filteredImages = useMemo(() => {
    const colorName = selectedColor
      ? getColorNameByCode(colorsName, selectedColor)
      : null;

    if (!colorName) {
      return galleryImages;
    }

    const variantPhotos = [];
    const generalPhotos = [];

    photos.forEach((photo) => {
      if (!photo.variant || photo.variant === "") {
        generalPhotos.push(photo);
      } else {
        variantPhotos.push(photo);
      }
    });

    const colorLower = colorName.toLowerCase();
    const matchingColorPhotos = variantPhotos.filter((p) => {
      const prefix = parseColorPrefix(p.variant).toLowerCase();
      return prefix === colorLower;
    });

    const exactMatch = selectedVariant
      ? matchingColorPhotos.find(
          (p) => p.variant.toLowerCase() === `${colorLower}-${selectedVariant}`.toLowerCase()
        )
      : null;

    const seen = new Set();
    const ordered = [];

    if (exactMatch) {
      ordered.push(exactMatch);
      seen.add(exactMatch.path);
    }

    matchingColorPhotos.forEach((p) => {
      if (!seen.has(p.path)) {
        ordered.push(p);
        seen.add(p.path);
      }
    });

    generalPhotos.forEach((p) => {
      if (!seen.has(p.path)) {
        ordered.push(p);
        seen.add(p.path);
      }
    });

    if (ordered.length === 0) return galleryImages;

    return ordered.map((photo, index) => ({
      id: index + 1,
      src: photo.path,
      alt: `${productData.name} - ${photo.variant || `Image ${index + 1}`}`,
    }));
  }, [selectedColor, selectedVariant, galleryImages, photos, colorsName, productData.name]);

  // "-20%" -> "-20%"; the API's own "-0%" for an undiscounted product, and a
  // missing value, both mean there is nothing to badge.
  const discountPercent = String(productData.discount ?? "").replace(/^-/, "");
  const discount =
    discountPercent && parseFloat(discountPercent) > 0 ? `-${discountPercent}` : null;

  return (
    <>
      <ImageGallery galleryImages={filteredImages} discount={discount} />
      <Details
        productData={productData}
        selectedColor={selectedColor}
        selectedVariant={selectedVariant}
        onColorChange={setSelectedColor}
        onVariantChange={setSelectedVariant}
      />
    </>
  );
}
