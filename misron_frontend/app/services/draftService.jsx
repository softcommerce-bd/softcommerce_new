// app/services/draftService.js

const API_URL = process.env.NEXT_PUBLIC_API_URL;

export const draftService = {
    // Create or update draft
    async createOrUpdate(draftData, sessionId = null) {
        try {
            const response = await fetch(`${API_URL}/draft/create-or-update`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    ...draftData,
                    session_id: sessionId || this.getSessionId()
                }),
            });
            
            const data = await response.json();
            // console.log('Is that data found here',data);
            return data;
        } catch (error) {
            console.error('Error saving draft:', error);
            throw error;
        }
    },
    
    // Get draft
    async getDraft(sessionId = null) {
        try {
            const response = await fetch(`${API_URL}/draft/get?session_id=${sessionId || this.getSessionId()}`);
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error fetching draft:', error);
            throw error;
        }
    },
    
    // Delete draft
    async deleteDraft(sessionId = null) {
        try {
            const response = await fetch(`${API_URL}/draft/delete`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    session_id: sessionId || this.getSessionId()
                }),
            });
            
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error deleting draft:', error);
            throw error;
        }
    },
    
    // Generate or get session ID
    getSessionId() {
        let sessionId = localStorage.getItem('draft_session_id');
        if (!sessionId) {
            sessionId = this.generateSessionId();
            localStorage.setItem('draft_session_id', sessionId);
        }
        return sessionId;
    },
    
    // Generate unique session ID
    generateSessionId() {
        return 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
};