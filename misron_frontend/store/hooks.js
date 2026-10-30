// store/hooks.js
import { useDispatch, useSelector } from 'react-redux';

// Regular JS hooks
export const useAppDispatch = () => useDispatch();
export const useAppSelector = useSelector;

// If you want to access store directly in components
import { store } from './store';
export const getState = () => store.getState();
export const dispatch = store.dispatch;