// app/hooks/useDraft.js

import { useState, useEffect, useCallback, useRef } from 'react';
import { draftService } from '@/app/services/draftService';

export const useDraft = (cartData) => {
    const [draftData, setDraftData] = useState(null);
    const [loading, setLoading] = useState(false);
    const debounceTimerRef = useRef(null);

    // Load draft on mount
    useEffect(() => {
        loadDraft();
    }, []);

    const loadDraft = async () => {
        try {
            const response = await draftService.getDraft();
            if (response.success && response.data) {
                setDraftData(response.data);
                return response.data;
            }
        } catch (error) {
            console.error('Error loading draft:', error);
        }
        return null;
    };

    const saveDraft = useCallback(async (data) => {
        if (debounceTimerRef.current) {
            clearTimeout(debounceTimerRef.current);
        }

        debounceTimerRef.current = setTimeout(async () => {
            try {
                setLoading(true);
                
                // Include cart products
                const draftPayload = {
                    ...data,
                    products: cartData ? JSON.stringify(cartData) : data.products,
                };
                
                const response = await draftService.createOrUpdate(draftPayload);
                
                if (response.success) {
                    setDraftData(response.data);
                }
            } catch (error) {
                console.error('Error saving draft:', error);
            } finally {
                setLoading(false);
            }
        }, 500);
    }, [cartData]);

    const deleteDraft = async () => {
        try {
            setLoading(true);
            const response = await draftService.deleteDraft();
            if (response.success) {
                setDraftData(null);
            }
            return response;
        } catch (error) {
            console.error('Error deleting draft:', error);
            throw error;
        } finally {
            setLoading(false);
        }
    };

    return {
        draftData,
        loading,
        saveDraft,
        deleteDraft,
        loadDraft
    };
};