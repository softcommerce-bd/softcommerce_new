// store/slices/videoModalSlice.js
import { createSlice } from "@reduxjs/toolkit";

const initialState = {
  isOpen: false,
  videoItem: null,
};

const videoModalSlice = createSlice({
  name: "videoModal",
  initialState,
  reducers: {
    openVideoModal: (state, action) => {
      state.isOpen = true;
      state.videoItem = action.payload;
    },
    closeVideoModal: (state) => {
      state.isOpen = false;
      state.videoItem = null;
    },
  },
});

export const { openVideoModal, closeVideoModal } = videoModalSlice.actions;
export default videoModalSlice.reducer;